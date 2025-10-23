<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <title>Mật khẩu mới</title>
    <style>
        .card {
            max-width: 520px;
            margin: 0 auto;
            padding: 20px;
            border: 1px solid #eee;
            border-radius: 10px;
            font-family: Arial, sans-serif;
        }

        .title {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .muted {
            color: #666;
            font-size: 14px;
        }

        .pwd {
            display: inline-block;
            padding: 8px 12px;
            border-radius: 6px;
            background: #f3f4f6;
            font-weight: 700;
            margin: 12px 0;
        }
    </style>
</head>

<body>
    <div class="card">
        <div class="title">Xin chào {{ $user->name ?? ($user->username ?? 'bạn') }},</div>
        <p class="muted">Bạn vừa yêu cầu đặt lại mật khẩu. Dưới đây là mật khẩu mới của bạn:</p>
        <div class="pwd">{{ $newPassword }}</div>

        <p>Hãy dùng mật khẩu này để đăng nhập, và đổi lại mật khẩu trong phần tài khoản để đảm bảo an toàn.</p>

        <p class="muted">Nếu bạn không yêu cầu thao tác này, hãy bỏ qua email.</p>

        <p>Trân trọng,<br>TheThao Team</p>
    </div>
</body>

</html>
