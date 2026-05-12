<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>HFCCF Password Reset OTP</title>
</head>
<body style="font-family: Arial, sans-serif; background:#f4f7fb; padding:40px;">

    <div style="
        max-width:600px;
        margin:auto;
        background:white;
        border-radius:12px;
        padding:40px;
        border:1px solid #e5e7eb;
    ">

        <h2 style="color:#0ea5e9; margin-bottom:20px;">
            HFCCF Password Reset
        </h2>

        <p>Hello,</p>

        <p>
            We received a request to reset your password.
        </p>

        <p>
            Use the OTP code below:
        </p>

        <div style="
            font-size:32px;
            font-weight:bold;
            letter-spacing:8px;
            color:#111827;
            background:#f3f4f6;
            padding:20px;
            text-align:center;
            border-radius:10px;
            margin:30px 0;
        ">
            {{ $otp }}
        </div>

        <p>
            This code will expire in 10 minutes.
        </p>

        <p>
            Email: <strong>{{ $email }}</strong>
        </p>

        <hr style="margin:30px 0; border:none; border-top:1px solid #e5e7eb;">

        <p style="font-size:14px; color:#6b7280;">
            If you did not request a password reset, please ignore this email.
        </p>

    </div>

</body>
</html>
