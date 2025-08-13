<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Server Error - {{ config('app.name') }}</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #2c3e50 0%, #4a6741 100%);
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
            color: #2c3e50;
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
            background: #2c3e50;
            color: white;
            padding: 12px 24px;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 500;
            transition: background 0.3s ease;
            margin-right: 1rem;
        }
        .back-button:hover {
            background: #34495e;
        }
        .retry-button {
            display: inline-block;
            background: #27ae60;
            color: white;
            padding: 12px 24px;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 500;
            transition: background 0.3s ease;
        }
        .retry-button:hover {
            background: #2ecc71;
        }
        .request-id {
            margin-top: 2rem;
            font-size: 0.8rem;
            color: #999;
        }
        .support-info {
            margin-top: 1rem;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 8px;
            font-size: 0.9rem;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="error-code">500</div>
        <h1 class="error-title">Server Error</h1>
        <p class="error-message">
            Something went wrong on our end. We've been notified and are working to fix the issue.
        </p>
        
        <a href="{{ url('/') }}" class="back-button">
            ← Back to Home
        </a>
        <a href="javascript:location.reload()" class="retry-button">
            Try Again
        </a>

        @if(request()->header('X-Request-ID'))
            <div class="request-id">
                Request ID: {{ request()->header('X-Request-ID') }}
            </div>
        @endif

        <div class="support-info">
            If this problem persists, please contact our support team with the request ID above.
        </div>
    </div>
</body>
</html>
