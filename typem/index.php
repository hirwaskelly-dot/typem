
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TYPEM - Connect Freely</title>

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: #101827;
            color: white;
            font-family: Arial, sans-serif;
        }

        header {
            padding: 25px 8%;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            font-size: 30px;
            font-weight: bold;
            color: #60a5fa;
        }

        nav a {
            color: white;
            text-decoration: none;
            margin-left: 20px;
        }

        .hero {
            min-height: 80vh;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
            padding: 30px;
        }

        .hero h1 {
            font-size: 55px;
            margin-bottom: 20px;
        }

        .hero h1 span {
            color: #60a5fa;
        }

        .hero p {
            color: #b8c4d8;
            font-size: 18px;
            margin-bottom: 35px;
        }

        .buttons a {
            display: inline-block;
            padding: 14px 28px;
            margin: 8px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: bold;
        }

        .login {
            background: #2563eb;
            color: white;
        }

        .register {
            border: 1px solid #60a5fa;
            color: white;
        }

        footer {
            text-align: center;
            padding: 20px;
            color: #8290a7;
        }

        @media(max-width: 600px) {
            .hero h1 {
                font-size: 38px;
            }

            header {
                padding: 20px;
            }
        }
    </style>
</head>

<body>

<header>
    <div class="logo">TYPEM.</div>

    <nav>
        <a href="login.php">Login</a>
        <a href="register.php">Join</a>
    </nav>
</header>

<section class="hero">

    <h1>Your world.<br>
    Your <span>conversations.</span></h1>

    <p>
        Connect with your friends.
        Share moments and stay together.
    </p>

    <div class="buttons">
        <a href="login.php" class="login">Login</a>
        <a href="register.php" class="register">
            Create Account
        </a>
    </div>

</section>

<footer>
    © 2026 TYPEM. All rights reserved.
</footer>

</body>
</html>
