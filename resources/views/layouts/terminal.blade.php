<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GoldED 7</title>
    @vite(['resources/css/app.css'])
    @livewireStyles
</head>
<body class="golded-body">
    {{ $slot }}
    @livewireScripts
</body>
</html>
