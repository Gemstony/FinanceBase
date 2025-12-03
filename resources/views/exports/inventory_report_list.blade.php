<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>{{ $title ?? 'Inventory Report' }}</title>
  <style>
    body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 12px; color: #222; }
    h2 { margin: 0 0 12px 0; font-size: 18px; }
    table { width: 100%; border-collapse: collapse; }
    th, td { border: 1px solid #ddd; padding: 6px 8px; }
    thead th { background: #f3f4f6; text-align: left; }
    tfoot td { font-weight: bold; }
  </style>
</head>
<body>
  <h2>{{ $title ?? 'Inventory Report' }}</h2>
  <table>
    <thead>
      <tr>
        @foreach(($headers ?? []) as $h)
          <th>{{ $h }}</th>
        @endforeach
      </tr>
    </thead>
    <tbody>
      @forelse(($rows ?? []) as $r)
        <tr>
          @foreach($r as $cell)
            <td>{{ is_numeric($cell) ? number_format($cell, is_float($cell) ? 2 : 0) : $cell }}</td>
          @endforeach
        </tr>
      @empty
        <tr><td colspan="{{ count($headers ?? []) }}" style="text-align:center; color:#777;">No data</td></tr>
      @endforelse
    </tbody>
  </table>
</body>
</html>
