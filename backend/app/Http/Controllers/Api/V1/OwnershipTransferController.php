<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\OneOffCharge;
use App\Models\OwnershipTransfer;
use App\Models\OwnershipTransferDocument;
use App\Models\Unit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Ownership/tenancy change tracking per unit (sale, inheritance, gift),
 * with transfer-fee charging and document attachments. See
 * docs/decisions.md - "Added modules" for why this is separate from
 * plain unit editing: it's an append-only ledger, not an overwrite.
 */
class OwnershipTransferController extends Controller
{
    public function index(Request $request)
    {
        return response()->json(
            OwnershipTransfer::with(['unit:id,unit_number,reference_number', 'documents'])
                ->when($request->query('unit_id'), fn ($q, $v) => $q->where('unit_id', $v))
                ->when($request->query('status'), fn ($q, $v) => $q->where('status', $v))
                ->latest('transfer_date')
                ->paginate($request->integer('per_page', 25))
        );
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'unit_id' => ['required', 'integer', 'exists:units,id'],
            'transfer_type' => ['required', 'in:sale,inheritance,gift,other'],
            'new_owner_name' => ['required', 'string', 'max:255'],
            'new_owner_cnic' => ['nullable', 'string', 'max:20'],
            'new_owner_phone' => ['nullable', 'string', 'max:20'],
            'transfer_date' => ['required', 'date'],
            'sale_price' => ['nullable', 'numeric', 'min:0'],
            'transfer_fee' => ['nullable', 'numeric', 'min:0'],
        ]);

        $unit = Unit::findOrFail($data['unit_id']);

        $transfer = OwnershipTransfer::create([
            ...$data,
            'previous_owner_name' => $unit->owner_name ?? $unit->occupant_name ?? 'Unknown',
            'previous_owner_cnic' => null,
            'transfer_fee' => $data['transfer_fee'] ?? 0,
            'status' => 'pending',
        ]);

        return response()->json($transfer->load('unit'), 201);
    }

    public function uploadDocument(Request $request, OwnershipTransfer $ownershipTransfer)
    {
        $data = $request->validate([
            'label' => ['required', 'string', 'max:255'],
            'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ]);

        $path = $request->file('file')->store('ownership-transfers/'.$ownershipTransfer->id, 'local');

        $document = OwnershipTransferDocument::create([
            'ownership_transfer_id' => $ownershipTransfer->id,
            'label' => $data['label'],
            'file_path' => $path,
            'uploaded_by' => $request->user()->id,
        ]);

        return response()->json($document, 201);
    }

    public function downloadDocument(OwnershipTransferDocument $document)
    {
        abort_unless(
            OwnershipTransfer::where('id', $document->ownership_transfer_id)->exists(),
            404
        );

        return Storage::disk('local')->download($document->file_path);
    }

    public function approve(Request $request, OwnershipTransfer $ownershipTransfer)
    {
        abort_unless($ownershipTransfer->status === 'pending', 422, 'Only pending transfers can be approved.');

        $ownershipTransfer->update([
            'status' => 'approved',
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
        ]);

        return response()->json($ownershipTransfer);
    }

    public function reject(Request $request, OwnershipTransfer $ownershipTransfer)
    {
        $data = $request->validate(['rejection_reason' => ['required', 'string']]);

        abort_unless($ownershipTransfer->status === 'pending', 422, 'Only pending transfers can be rejected.');

        $ownershipTransfer->update(['status' => 'rejected', 'rejection_reason' => $data['rejection_reason']]);

        return response()->json($ownershipTransfer);
    }

    /**
     * Finalize: updates the unit's owner record and releases its app
     * link (the new owner/tenant verifies fresh - see the Auth module's
     * "release unit" behavior in docs/decisions.md). Optionally raises a
     * one-off charge for the transfer fee.
     */
    public function complete(Request $request, OwnershipTransfer $ownershipTransfer)
    {
        abort_unless($ownershipTransfer->status === 'approved', 422, 'Only approved transfers can be completed.');

        DB::transaction(function () use ($ownershipTransfer, $request) {
            $unit = $ownershipTransfer->unit;
            $oldLinkedUser = $unit->linked_user_id;

            $unit->forceFill([
                'owner_name' => $ownershipTransfer->new_owner_name,
                'occupant_name' => $ownershipTransfer->new_owner_name,
                'linked_user_id' => null,
                'linked_at' => null,
            ])->save();

            if ($oldLinkedUser) {
                AuditLog::record('unit.released', $unit, ['linked_user_id' => $oldLinkedUser], ['linked_user_id' => null]);
            }

            if ($ownershipTransfer->transfer_fee > 0) {
                $charge = OneOffCharge::create([
                    'unit_id' => $unit->id,
                    'title' => 'Ownership Transfer Fee',
                    'amount' => $ownershipTransfer->transfer_fee,
                    'reason' => "Transfer fee for ownership change ({$ownershipTransfer->transfer_type})",
                    'source_type' => OwnershipTransfer::class,
                    'source_id' => $ownershipTransfer->id,
                    'status' => 'pending',
                ]);
                $ownershipTransfer->one_off_charge_id = $charge->id;
            }

            $ownershipTransfer->status = 'completed';
            $ownershipTransfer->completed_at = now();
            $ownershipTransfer->save();
        });

        return response()->json($ownershipTransfer->fresh()->load('unit'));
    }
}
