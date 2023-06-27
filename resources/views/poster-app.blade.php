<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Salesbox poster</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;700&display=swap"
        rel="stylesheet"
    />
    <link
        rel="stylesheet"
        href="https://unpkg.com/tachyons@4.12.0/css/tachyons.min.css"
    />
    <script type="text/javascript" language="javascript" src="https://code.jquery.com/jquery-3.5.1.js"></script>
    <script type="text/javascript" language="javascript" src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <style>
        *,
        *:after,
        *:before {
            box-sizing: border-box;
        }
        body {
            font-family: "Inter", sans-serif;
            padding: 40px;
            margin: 0;
        }
        p {
            margin: 0;
        }

        .dataTables_paginate {
            display: flex;
            justify-content: flex-end;
            width: 376px;
        }

        .paginate_button {
            display: inline-flex;
            justify-content: center;
            align-items: center;
            font-size: 10px;
            width: 20px;
            height: 20px;
            margin-top: 4px;
            margin-left: 4px;
            cursor: pointer;
        }

        .paginate_button.current, .paginate_button:hover {
            background-color: black;
            color: white;
        }

        .paginate_button.current {
            font-weight: 600;
        }

        .paginate_button.next, .paginate_button.previous {
            display: none;
        }
    </style>
</head>
<body>
<div>
    <p class="fw6 f5 lh-copy">Тести</p>
    <p class="mt2 f7">
        @if(count($products) > 0)
            ⚠️Товари не синхронізовані
        @else
            ✅ Товари синхронізовані
        @endif
    </p>
    <p class="mt2 f7">
        @if(count($categories) > 0)
            ⚠️Категорії не синхронізовані
        @else
            ✅ Категорії синхронізовані
        @endif
    </p>
</div>
@if(count($categories) > 0)
<div style="margin-top: 78px">
    <p class="fw6 f5 lh-copy">Категорії</p>
    <table id="categoryTable" style="border-collapse: collapse" class="f7 ba b--black mt3">
        <thead>
        <tr>
            <th width="138" class="ph2 pv1"></th>
            <th width="79" class="fw4 ba b--black ph2 pv1 tc">Salesbox</th>
            <th width="79" class="fw4 ba b--black ph2 pv1 tc">Poster</th>
            <th width="79" class="fw4 ba b--black ph2 pv1 tc">Звязок між системами</th>
        </tr>
        </thead>
        <tbody>
        @foreach($categories as $category)
            <tr>
                <td class="ba b--black ph2 pv1">
                    <span style="width: 121px" class="truncate dib" title="{{ $category['name'] }}"> {{ $category['name'] }} </span>
                </td>
                <td class="ba b--black ph2 pv1 tc">{{ $category['salesbox'] ? '✅': '❌' }}</td>
                <td class="ba b--black ph2 pv1 tc">{{ $category['poster'] ? '✅': '❌' }}</td>
                <td class="ba b--black ph2 pv1 tc"> {{ $category['connected'] ?  '✅': '❌' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="mt1">
        <button class="f7 bg-black white bn pv2 ph3 pointer dim">
            Синхронізувати
        </button>
    </div>
</div>
@endif
@if(count($products) > 0)
<div style="margin-top: 50px">
    <p class="fw6 f5 lh-copy">Товари</p>
    <table id="productTable" style="border-collapse: collapse" class="f7 ba b--black mt3">
        <thead>
        <tr>
            <th width="138" class="ph2 pv1"></th>
            <th width="79" class="fw4 ba b--black ph2 pv1 tc">Salesbox</th>
            <th width="79" class="fw4 ba b--black ph2 pv1 tc">Poster</th>
            <th width="79" class="fw4 ba b--black ph2 pv1 tc">Звязок між системами</th>
        </tr>
        </thead>
        <tbody>
        @foreach($products as $product)
        <tr>
            <td class="ba b--black pv1 ph2">
              <span style="width: 121px" class="truncate dib" title="{{ $product['name'] }}">
                {{ $product['name'] }}
              </span>
            </td>
            <td class="ba b--black ph2 pv1 tc"> {{ $product['salesbox'] ? '✅': '❌' }}</td>
            <td class="ba b--black ph2 pv1 tc"> {{ $product['poster'] ? '✅': '❌' }}</td>
            <td class="ba b--black ph2 pv1 tc"> {{ $product['connected'] ? '✅' : '❌' }}</td>
        </tr>
        @endforeach

        </tbody>
    </table>

    <div class="mt1">
        <button class="f7 bg-black white bn pv2 ph3 pointer dim">
            Синхронізувати
        </button>
    </div>
</div>
@endif
<script>
    window.addEventListener('load', function () {
        top.postMessage({hideSpinner: true}, '*');
    }, false)
</script>
<script>
    $('#categoryTable').DataTable({
        searching: false,
        ordering: false,
        paging: true,
        autoWidth: false,
        info: false,
        bLengthChange: false
    });
    $('#productTable').DataTable({
        searching: false,
        ordering: false,
        paging: true,
        autoWidth: false,
        info: false,
        bLengthChange: false
    });
</script>
</body>
</html>
