<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pedido #{{ $order->id ?? '' }} - Erden</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <?php
    $protocol = 'http';
    if (
        (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
        (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ||
        (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on')
    ) {
        $protocol = 'https';
    }
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    ?>
    <style>
        body { margin: 0; padding: 0; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; }
        .order-display-card { background: white; border-radius: 15px; box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2); max-width: 600px; width: 100%; margin: 20px; overflow: hidden; }
        .card-header-custom { padding: 20px; display: flex; justify-content: space-between; align-items: center; }
        .card-body-custom { padding: 20px; }
        .order-items table { margin-bottom: 0; }
        .order-items th { background-color: #f8f9fa; font-weight: 600; }
        .order-total { border-top: 3px solid #28a745; padding-top: 15px; }
        .badge-custom { font-size: 0.9rem; padding: 8px 12px; }
        .order-selector select { font-size: 1.1rem; padding: 10px; }
    </style>
</head>
<body>
    @if($hasOrder && $order)
    <div class="order-display-card">
        <div class="card-header-custom bg-primary text-white">
            <h4 class="mb-0"><i class="bi bi-receipt"></i> Pedido #{{ $order->id }}</h4>
            <span class="badge-custom bg-{{ $order->status_name === 'completed' ? 'success' : ($order->status_name === 'cancelled' ? 'danger' : 'warning') }}">
                {{ $order->status_name }}
            </span>
        </div>
        <div class="card-body-custom">
            <div class="order-info mb-4">
                <div class="row">
                    <div class="col-6"><strong>Operador:</strong> {{ $order->operator_name }}</div>
                    <div class="col-6 text-end"><strong>Fecha:</strong> {{ \Carbon\Carbon::parse($order->created_at)->format('d/m/Y H:i') }}</div>
                </div>
            </div>
            <div class="order-items">
                <h5 class="border-bottom pb-2">Items del Pedido</h5>
                <table class="table table-sm">
                    <thead><tr><th>Cant.</th><th>Producto</th><th class="text-end">Precio</th><th class="text-end">Subtotal</th></tr></thead>
                    <tbody>
                        @php $details = is_array($order->detail) ? $order->detail : json_decode($order->detail, true); @endphp
                        @foreach($details as $item)
                        <tr>
                            <td>{{ $item['quantity'] ?? $item->quantity }}</td>
                            <td>{{ $item['name'] ?? $item->name }}</td>
                            <td class="text-end">${{ number_format($item['price'] ?? $item->price, 2) }}</td>
                            <td class="text-end">${{ number_format(($item['quantity'] ?? $item->quantity) * ($item['price'] ?? $item->price), 2) }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="order-total mt-4">
                <div class="d-flex justify-content-between align-items-center">
                    <h3 class="mb-0">Total:</h3>
                    <h3 class="mb-0 text-success">${{ number_format($order->total, 2) }}</h3>
                </div>
            </div>
            @if($order->paid)
            <div class="mt-3">
                <span class="badge-custom bg-success fs-5"><i class="bi bi-check-circle"></i> PAGADO</span>
            </div>
            @endif
            <div class="order-selector mt-4">
                <label for="orderSelect" class="form-label">Ver otro pedido:</label>
                <select id="orderSelect" class="form-select" onchange="window.location.href='/pedido/{{ $username }}/' + this.value">
                    @foreach($orders as $opt)
                    <option value="{{ $opt->id }}" {{ $opt->id == $order->id ? 'selected' : '' }}>
                        #{{ $opt->id }} - ${{ number_format($opt->total, 2) }} - {{ \Carbon\Carbon::parse($opt->created_at)->format('d/m H:i') }} ({{ $opt->status_name }})
                    </option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>
    @else
    <div class="order-display-card">
        <div class="card-header-custom bg-warning text-dark">
            <h4 class="mb-0"><i class="bi bi-exclamation-triangle"></i> Sin Pedidos</h4>
        </div>
        <div class="card-body-custom text-center">
            <p class="lead">No hay pedidos para mostrar</p>
            <a href="/login" class="btn btn-primary">Ir al Login</a>
        </div>
    </div>
    @endif
</body>
</html>