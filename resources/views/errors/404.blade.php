<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Page Not Found - {{ config('app.name') }}</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            margin: 0;
            padding: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #333;
        }
        .error-container {
            text-align: center;
            background: white;
            padding: 3rem;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            max-width: 500px;
            margin: 2rem;
        }
        .error-code {
            font-size: 8rem;
            font-weight: bold;
            color: #667eea;
            margin: 0;
            line-height: 1;
        }
        .error-title {
            font-size: 2rem;
            margin: 1rem 0;
            color: #333;
        }
        .error-message {
            font-size: 1.1rem;
            color: #666;
            margin-bottom: 2rem;
            line-height: 1.5;
        }
        .back-button {
            display: inline-block;
            background: #667eea;
            color: white;
            padding: 12px 24px;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 500;
            transition: background 0.3s ease;
        }
        .back-button:hover {
            background: #5a6fd8;
        }
        .request-id {
            margin-top: 2rem;
            font-size: 0.8rem;
            color: #999;
        }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="error-code">404</div>
        <h1 class="error-title">Page Not Found</h1>
        <p class="error-message">
            The page you're looking for doesn't exist. It might have been moved, deleted, or you entered the wrong URL.
        </p>
        <a href="{{ url('/') }}" class="back-button">
            ← Back to Home
        </a>
        @if(request()->header('X-Request-ID'))
            <div class="request-id">
                Request ID: {{ request()->header('X-Request-ID') }}
            </div>
        @endif
    </div>
</body>
</html>
