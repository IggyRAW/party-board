<!DOCTYPE html>
<html lang="ja">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title inertia>Party Board</title>
        <script>
            (function () {
                try {
                    if (localStorage.getItem('party-board-theme') === 'bright') {
                        document.documentElement.dataset.theme = 'bright';
                    }
                } catch (e) {}
            })();
        </script>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @inertiaHead
    </head>
    <body>
        @inertia
    </body>
</html>
