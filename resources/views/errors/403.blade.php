<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Access Denied - {{ config('app.name') }}</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a24 100%);
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
            color: #ff6b6b;
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
            background: #ff6b6b;
            color: white;
            padding: 12px 24px;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 500;
            transition: background 0.3s ease;
            margin-right: 1rem;
        }
        .back-button:hover {
            background: #ff5252;
        }
        .login-button {
            display: inline-block;
            background: #333;
            color: white;
            padding: 12px 24px;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 500;
            transition: background 0.3s ease;
        }
        .login-button:hover {
            background: #555;
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
        <div class="error-code">403</div>
        <h1 class="error-title">Access Denied</h1>
        <p class="error-message">
            You don't have permission to access this resource. Please contact your administrator if you believe this is an error.
        </p>
        <a href="{{ url('/') }}" class="back-button">
            ← Back to Home
        </a>
        @guest
            <a href="{{ route('login') }}" class="login-button">
                Login
            </a>
        @endguest
        @if(request()->header('X-Request-ID'))
            <div class="request-id">
                Request ID: {{ request()->header('X-Request-ID') }}
            </div>
        @endif
    </div>
</body>
</html>
