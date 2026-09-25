<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Gift message</title>
    <style>
        body {
            font-family: sans-serif;
            font-size: 14pt;
        }
        .card {
            display: table;
            width: 100%;
            height: 100%;
        }
        .card-inner {
            display: table-cell;
            vertical-align: middle;
            text-align: center;
        }
        .message {
            white-space: pre-wrap;
            line-height: 1.5;
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="card-inner">
            <div class="message">{{ $message }}</div>
        </div>
    </div>
</body>
</html>
