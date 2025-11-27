<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Export des données</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 10px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        th, td {
            border: 1px solid #222;
            padding: 4px 6px;
            text-align: left;
        }
        th {
            background-color: #f0f0f0;
            font-weight: bold;
        }
        h1 {
            font-size: 16px;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
    <h1>Export des données</h1>
    <table>
        <thead>
        @if(!empty($data) && is_array($data) && count($data) > 0)
            <tr>
                @php
                    $firstRow = is_array($data[0]) ? $data[0] : (is_object($data[0]) ? (array) $data[0] : []);
                    $headers = array_keys($firstRow);
                @endphp
                @foreach($headers as $header)
                    <th>{{ $header }}</th>
                @endforeach
            </tr>
        @endif
        </thead>
        <tbody>
        @forelse($data as $row)
            <tr>
                @php
                    $rowArray = is_array($row) ? $row : (is_object($row) ? (array) $row : []);
                @endphp
                @foreach($headers ?? [] as $header)
                    <td>
                        @php
                            $value = $rowArray[$header] ?? null;
                        @endphp
                        @if(is_array($value) || is_object($value))
                            {{ json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}
                        @elseif(is_bool($value))
                            {{ $value ? 'Oui' : 'Non' }}
                        @elseif(is_null($value))
                            -
                        @else
                            {{ $value }}
                        @endif
                    </td>
                @endforeach
            </tr>
        @empty
            <tr>
                <td colspan="{{ count($headers ?? []) ?: 10 }}">Aucune donnée disponible</td>
            </tr>
        @endforelse
        </tbody>
    </table>
</body>
</html>










