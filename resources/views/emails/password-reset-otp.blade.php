<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>HFCCF Password Reset OTP</title>
</head>
<body style="margin:0; padding:0; background:#eef6fb; font-family:Arial, Helvetica, sans-serif; color:#0f172a;">

<table width="100%" cellpadding="0" cellspacing="0" style="background:#eef6fb; padding:36px 16px;">
    <tr>
        <td align="center">
            <table width="100%" cellpadding="0" cellspacing="0" style="max-width:620px; background:#ffffff; border-radius:20px; overflow:hidden; border:1px solid #dbe4ee; box-shadow:0 24px 60px rgba(15,23,42,0.12);">
                <tr>
                    <td style="height:8px; background:linear-gradient(90deg,#00aeef,#8dc63f,#fdc116);"></td>
                </tr>

                <tr>
                    <td style="padding:34px 34px 18px; text-align:center;">
                        <div style="display:inline-block; padding:10px 18px; border-radius:999px; background:#f0f9ff; border:1px solid #bae6fd; color:#0369a1; font-size:12px; font-weight:800; letter-spacing:.04em; text-transform:uppercase;">
                            HFCCF Portal Security
                        </div>

                        <h1 style="margin:20px 0 8px; color:#0f172a; font-size:28px; line-height:1.2;">
                            Password Reset Verification
                        </h1>

                        <p style="margin:0; color:#64748b; font-size:15px; line-height:1.7;">
                            Use the 6-digit code below to continue resetting your HFCCF account password.
                        </p>
                    </td>
                </tr>

                <tr>
                    <td style="padding:18px 34px;">
                        <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:18px; padding:28px; text-align:center;">
                            <p style="margin:0 0 14px; color:#475569; font-size:14px; font-weight:700;">
                                Your verification code
                            </p>

                            <div style="display:inline-block; background:#ffffff; border:2px dashed #0ea5e9; border-radius:16px; padding:18px 28px;">
                                <span style="display:block; color:#0369a1; font-size:38px; line-height:1; font-weight:900; letter-spacing:10px;">
                                    {{ $otp }}
                                </span>
                            </div>

                            <p style="margin:18px 0 0; color:#64748b; font-size:14px; line-height:1.6;">
                                This code expires in <strong style="color:#0f172a;">10 minutes</strong>.
                            </p>
                        </div>
                    </td>
                </tr>

                <tr>
                    <td style="padding:4px 34px 28px;">
                        <table width="100%" cellpadding="0" cellspacing="0" style="background:#fff7ed; border:1px solid #fed7aa; border-radius:14px;">
                            <tr>
                                <td style="padding:16px 18px; color:#9a3412; font-size:14px; line-height:1.6;">
                                    If you did not request this password reset, you can safely ignore this email.
                                </td>
                            </tr>
                        </table>

                        <p style="margin:20px 0 0; color:#64748b; font-size:14px; line-height:1.7;">
                            Requested for:
                            <strong style="color:#0f172a;">{{ $email }}</strong>
                        </p>
                    </td>
                </tr>

                <tr>
                    <td style="padding:20px 34px 30px; background:#f8fafc; border-top:1px solid #e2e8f0; text-align:center;">
                        <p style="margin:0; color:#94a3b8; font-size:12px; line-height:1.6;">
                            © {{ date('Y') }} HFCCF Portal. This is an automated security email.
                        </p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>

</body>
</html>
