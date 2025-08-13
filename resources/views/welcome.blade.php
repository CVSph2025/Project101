<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Welcome to HomyGo</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <style>
    html, body {
      margin: 0;
      padding: 0;
      background: #fff;
      height: 100%;
      min-height: 100vh;
      width: 100vw;
      overflow: hidden;
    }
    .svg-fullscreen {
      display: flex;
      align-items: center;
      justify-content: center;
      height: 100vh;
      width: 100vw;
      background: #fff;
    }
    .svg-fullscreen img {
      width: 100vw;
      height: 100vh;
      object-fit: contain;
      max-width: 100vw;
      max-height: 100vh;
      background: #fff;
    }
    @media (max-width: 600px) {
      .svg-fullscreen img {
        width: 100vw;
        height: 100vh;
      }
    }
  </style>
  <script>
    setTimeout(function() {
      window.location.href = "{{ route('login') }}";
    }, 2000);
  </script>
</head>
<body>
  <div class="svg-fullscreen">
    <img src="{{ asset('HomyGo.svg') }}" alt="HomyGo Logo" onclick="window.location.href='{{ route('login') }}'" style="cursor:pointer;">
  </div>
</body>
</html>
