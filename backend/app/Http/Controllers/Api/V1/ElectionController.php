<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Election;
use App\Models\ElectionCandidate;
use App\Models\ElectionPosition;
use App\Models\ElectionResult;
use App\Models\ElectionVote;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ElectionController extends Controller
{
    public function index(Request $request)
    {
        return response()->json(
            Election::with('positions')
                ->when($request->query('status'), fn ($q, $v) => $q->where('status', $v))
                ->latest('voting_start')
                ->paginate($request->integer('per_page', 25))
        );
    }

    public function show(Election $election)
    {
        $election->load(['positions.candidates' => function ($q) {
            $q->where('status', 'approved');
        }]);

        return response()->json($election);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'nomination_start' => ['required', 'date'],
            'nomination_end' => ['required', 'date', 'after:nomination_start'],
            'voting_start' => ['required', 'date', 'after_or_equal:nomination_end'],
            'voting_end' => ['required', 'date', 'after:voting_start'],
            'results_visibility' => ['required', 'in:after_close,live'],
            'positions' => ['required', 'array', 'min:1'],
            'positions.*.title' => ['required', 'string', 'max:255'],
            'positions.*.seats_available' => ['required', 'integer', 'min:1'],
        ]);

        $election = DB::transaction(function () use ($data) {
            $election = Election::create([...collect($data)->except('positions')->all(), 'status' => 'draft']);
            foreach ($data['positions'] as $i => $p) {
                $election->positions()->create(['title' => $p['title'], 'seats_available' => $p['seats_available'], 'sort_order' => $i]);
            }

            return $election;
        });

        return response()->json($election->load('positions'), 201);
    }

    /** Admin: open nominations / open voting / close, in that order. */
    public function transition(Request $request, Election $election)
    {
        $data = $request->validate(['status' => ['required', 'in:nominations_open,voting_open,closed,cancelled']]);

        $allowed = [
            'draft' => ['nominations_open', 'cancelled'],
            'nominations_open' => ['voting_open', 'cancelled'],
            'voting_open' => ['closed', 'cancelled'],
        ];

        abort_unless(in_array($data['status'], $allowed[$election->status] ?? [], true), 422, "Cannot move election from {$election->status} to {$data['status']}.");

        $election->update(['status' => $data['status']]);

        return response()->json($election);
    }

    /** Resident: nominate self for a position during the nomination window. */
    public function nominate(Request $request, ElectionPosition $electionPosition)
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->isResident(), 403);

        $election = $electionPosition->election;
        abort_if($election === null, 404); // position belongs to another society's election
        abort_unless($election->status === 'nominations_open', 422, 'Nominations are not open.');
        abort_unless(now()->between($election->nomination_start, $election->nomination_end), 422, 'Outside the nomination window.');

        $unit = $user->units()->first();
        abort_if($unit === null, 422, 'You must have a linked unit to nominate yourself.');

        $data = $request->validate([
            'symbol' => ['nullable', 'string', 'max:50'],
            'manifesto' => ['nullable', 'string'],
        ]);

        $candidate = ElectionCandidate::create([
            'election_id' => $election->id,
            'election_position_id' => $electionPosition->id,
            'user_id' => $user->id,
            'unit_id' => $unit->id,
            'symbol' => $data['symbol'] ?? null,
            'manifesto' => $data['manifesto'] ?? null,
            'status' => 'nominated',
        ]);

        return response()->json($candidate, 201);
    }

    /** Admin: approve/reject a nomination. */
    public function reviewCandidate(Request $request, ElectionCandidate $electionCandidate)
    {
        abort_if(Election::where('id', $electionCandidate->election_id)->doesntExist(), 404);

        $data = $request->validate([
            'status' => ['required', 'in:approved,rejected'],
            'rejection_reason' => ['required_if:status,rejected', 'nullable', 'string'],
        ]);

        $electionCandidate->update([
            'status' => $data['status'],
            'approved_by' => $request->user()->id,
            'rejection_reason' => $data['rejection_reason'] ?? null,
        ]);

        return response()->json($electionCandidate);
    }

    /**
     * Resident: cast a secret ballot. One vote per unit per position,
     * enforced by a DB unique constraint - never exposed via any listing
     * (see docs/decisions.md).
     */
    public function vote(Request $request, ElectionPosition $electionPosition)
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->isResident(), 403);

        $election = $electionPosition->election;
        abort_if($election === null, 404); // position belongs to another society's election
        abort_unless($election->status === 'voting_open', 422, 'Voting is not open.');
        abort_unless(now()->between($election->voting_start, $election->voting_end), 422, 'Outside the voting window.');

        $unit = $user->units()->first();
        abort_if($unit === null, 422, 'You must have a linked unit to vote.');

        $data = $request->validate(['election_candidate_id' => ['required', 'integer', 'exists:election_candidates,id']]);

        $candidate = ElectionCandidate::where('id', $data['election_candidate_id'])
            ->where('election_position_id', $electionPosition->id)
            ->where('status', 'approved')
            ->firstOrFail();

        abort_if(
            ElectionVote::where('election_position_id', $electionPosition->id)->where('unit_id', $unit->id)->exists(),
            409,
            'This unit has already voted for this position.'
        );

        ElectionVote::create([
            'election_id' => $election->id,
            'election_position_id' => $electionPosition->id,
            'election_candidate_id' => $candidate->id,
            'unit_id' => $unit->id,
            'voted_at' => now(),
        ]);

        return response()->json(['message' => 'Vote recorded.']);
    }

    /** Admin: close voting and certify results (idempotent - recertifying overwrites the snapshot). */
    public function certifyResults(Request $request, Election $election)
    {
        abort_unless($election->status === 'closed', 422, 'Election must be closed before certifying results.');

        DB::transaction(function () use ($election, $request) {
            ElectionResult::where('election_id', $election->id)->delete();

            foreach ($election->positions as $position) {
                $tally = ElectionVote::where('election_position_id', $position->id)
                    ->selectRaw('election_candidate_id, COUNT(*) as votes')
                    ->groupBy('election_candidate_id')
                    ->orderByDesc('votes')
                    ->get();

                foreach ($tally as $rank => $row) {
                    ElectionResult::create([
                        'election_id' => $election->id,
                        'election_position_id' => $position->id,
                        'election_candidate_id' => $row->election_candidate_id,
                        'votes_count' => $row->votes,
                        'is_winner' => $rank < $position->seats_available,
                        'certified_by' => $request->user()->id,
                        'certified_at' => now(),
                    ]);
                }
            }

            $election->update(['results_published' => true]);
        });

        return response()->json(['message' => 'Results certified.']);
    }

    /** Aggregate-only results - never exposes which unit voted for whom. */
    public function results(Election $election)
    {
        abort_unless($election->canShowLiveResults(), 403, 'Results are not visible yet.');

        $results = ElectionResult::with('candidate.user:id,name')
            ->where('election_id', $election->id)
            ->orderByDesc('votes_count')
            ->get()
            ->groupBy('election_position_id');

        if ($results->isEmpty() && $election->results_visibility === 'live') {
            // Live mode with no certified snapshot yet: compute on the fly.
            $results = ElectionVote::where('election_id', $election->id)
                ->selectRaw('election_position_id, election_candidate_id, COUNT(*) as votes_count')
                ->groupBy('election_position_id', 'election_candidate_id')
                ->get()
                ->groupBy('election_position_id');
        }

        return response()->json($results);
    }
}
