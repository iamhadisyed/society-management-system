<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
@include('pdf._bill_styles')
</head>
<body>
@include('pdf._bill-copies', ['bill' => $bill, 'society' => $society, 'qrSvgBase64' => $qrSvgBase64, 'ad' => $ad])
</body>
</html>
