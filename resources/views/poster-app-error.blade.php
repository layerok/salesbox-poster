<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>Laravel</title>


    </head>
    <body >
        Error#{{ $error }}: {{ $message }}
       <script>
           window.addEventListener('load', function() {
               top.postMessage({ hideSpinner: true}, '*');
           }, false)
       </script>
    </body>


</html>
