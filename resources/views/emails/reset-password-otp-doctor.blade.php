<!DOCTYPE html>
<html lang="ar">
<head>
    <meta charset="UTF-8">
</head>
<body style="font-family: Arial; background:#f5f5f5; padding:20px">

    <div style="max-width:600px; background:#fff; margin:auto; padding:20px; text-align:center">

        {{-- شعار الشركة --}}
        <img src="{{ $message->embed(public_path('logo/IMG_3431.PNG')) }}"
             alt="Company Logo"
             style="max-width:150px; margin-bottom:20px">

        <h2>إعادة تعيين كلمة المرور</h2>

        <p>رمز التحقق الخاص بك هو:</p>

        <h1 style="letter-spacing:5px">{{ $otp }}</h1>

        <p style="color:#777">
            الرمز صالح لمدة 3 دقائق
        </p>

    </div>

</body>
</html>
