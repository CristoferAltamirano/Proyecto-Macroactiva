<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceso | Macroactiva</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    
    <style>
        body {
            background-color: #e9ecef;
        }
        .login-container {
            min-height: 100vh;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row login-container align-items-center justify-content-center">
            <div class="col-md-5 col-lg-4">
                <div class="card shadow-lg border-0 rounded-3">
                    <div class="card-body p-4 p-sm-5">
                        <div class="text-center mb-4">
                            <img src="/logo macroactiva.png" alt="Logo Macroactiva" style="max-width: 200px;">
                            <h3 class="card-title fw-bold mt-3">Acceso Corporativo</h3>
                        </div>

                        <form method="POST" action="{{ route('login.do') }}">
                            @csrf
                            <div class="form-floating mb-3">
                                <input type="email" name="email" class="form-control @error('email') is-invalid @enderror" id="floatingInput" placeholder="nombre@ejemplo.com" value="{{ old('email') }}" required autofocus>
                                <label for="floatingInput"><i class="bi bi-envelope me-2"></i>Correo Electrónico</label>
                                @error('email')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="form-floating mb-3">
                                <input type="password" name="password" class="form-control" id="floatingPassword" placeholder="Contraseña" required>
                                <label for="floatingPassword"><i class="bi bi-lock me-2"></i>Contraseña</label>
                            </div>

                            <div class="d-grid">
                                <button class="btn btn-primary btn-lg fw-bold" type="submit">Ingresar</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>