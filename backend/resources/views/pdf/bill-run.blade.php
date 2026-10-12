<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
@include('pdf._bill_styles')
</head>
<body>
@foreach ($bills as $bill)
    @include('pdf._bill-copies', ['bill' => $bill, 'society' => $society, 'qrSvgBase64' => $qrSvgBase64[$bill->id], 'ad' => $ad, 'printedBy' => $printedBy])
@endforeach
</body>
</html>
