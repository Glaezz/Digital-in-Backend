<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - Digital-in</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 0;
            background-color: #f4f4f4;
            color: #ffffff
        }

        .container {
            max-width: 600px;
            margin: 20px auto;
            padding: 20px;
            background-color: #070707;
            border-radius: 10px;
            color: #ffffff;
        }

        .logo-container {
            text-align: center;
            margin-bottom: 20px;
        }

        .logo {
            width: 50px;
            height: 50px;
            margin-bottom: 10px;
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
        }

        .app-name {
            font-size: 24px;
            font-weight: bold;
            color: #ffffff;
            margin: 10px 0;
        }

        .content {
            background-color: #181718;
            padding: 30px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .button {
            display: inline-block;
            padding: 12px 24px;
            background-color: #075400;
            text-decoration: none;
            color: #ffffff;
            border-radius: 50px;
            font-weight: bold;
            margin: 20px 0;
        }

        .disclaimer {
            font-size: 12px;
            color: #ffffff;
            text-align: center;
            margin-top: 20px;
            padding: 20px;
            border-top: 1px solid #333;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="logo-container">
            <svg width="50" height="50" viewBox="0 0 24 24" class="logo">
                <path fill="#ffffff" d="M22.672 15.226l-2.432.811.841 2.515c.33 1.019-.209 2.127-1.23 2.456-1.15.325-2.148-.321-2.463-1.226l-.84-2.518-5.013 1.677.84 2.517c.391 1.203-.434 2.542-1.831 2.542-.88 0-1.601-.564-1.86-1.314l-.842-2.516-2.431.809c-1.135.328-2.145-.317-2.463-1.229-.329-1.018.211-2.127 1.231-2.456l2.432-.809-1.621-4.823-2.432.808c-1.355.384-2.558-.59-2.558-1.839 0-.817.509-1.582 1.327-1.846l2.433-.809-.842-2.515c-.33-1.02.211-2.129 1.232-2.458 1.02-.329 2.13.209 2.461 1.229l.842 2.515 5.011-1.677-.839-2.517c-.403-1.238.484-2.553 1.843-2.553.819 0 1.585.509 1.85 1.326l.841 2.517 2.431-.81c1.02-.33 2.131.211 2.461 1.229.332 1.018-.21 2.126-1.23 2.456l-2.433.809 1.622 4.823 2.433-.809c1.242-.401 2.557.484 2.557 1.838 0 .819-.51 1.583-1.328 1.847m-8.992-6.428l-5.01 1.675 1.619 4.828 5.011-1.674-1.62-4.829z"></path>
            </svg>
            <div class="app-name">Digital-in</div>
        </div>

        <div class="content">
            <h2>Reset Password</h2>
            <p>Hello {{$data["username"]}},</p>
            <p>We accept requests to reset your Digital-in account password. Click the button below to continue the password reset process:</p>

            <center>
                <a href="{{$data["reset_url"]}}" class="button">Reset Password</a>
            </center>

            <p>If you don't feel like making this request, you can ignore this email or contact our support team for further assistance.</p>

            <!-- <p>Link reset password ini akan kedaluwarsa dalam 60 menit.</p> -->
        </div>

        <div class="disclaimer">
            <p>This email is sent automatically, please do not reply to this email.</p>
            <!-- <p>For assistance, please contact our support.</p> -->
            <p>© 2024 Digital-in. All Rights Reserved.</p>
        </div>
    </div>
</body>

</html>