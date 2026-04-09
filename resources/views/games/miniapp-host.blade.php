<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ ucfirst($gameSlug) }} - MiniApp Web</title>
    <style>
        html, body {
            margin: 0;
            padding: 0;
            width: 100%;
            height: 100%;
            background: #0b1220;
            overflow: hidden;
        }

        .miniapp-frame {
            width: 100%;
            height: 100%;
            border: 0;
            display: block;
            background: #0b1220;
        }
    </style>
</head>
<body>
    <iframe
        class="miniapp-frame"
        src="{{ $miniAppUrl }}"
        title="MiniApp {{ $gameSlug }}"
        loading="eager"
    ></iframe>
</body>
</html>
