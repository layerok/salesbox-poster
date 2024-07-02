<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"/>
    <meta http-equiv="X-UA-Compatible" content="IE=edge"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Salesbox poster</title>
    <link rel="preconnect" href="https://fonts.googleapis.com"/>
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;700&display=swap"
        rel="stylesheet"
    />
    <link
        rel="stylesheet"
        href="https://unpkg.com/tachyons@4.12.0/css/tachyons.min.css"
    />
    <script type="text/javascript" language="javascript" src="https://code.jquery.com/jquery-3.5.1.js"></script>
    <script type="text/javascript" language="javascript"
            src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>

    <style>
        *,
        *:after,
        *:before {
            box-sizing: border-box;
        }

        body {
            font-family: "Inter", sans-serif;
           padding: 0;
            margin: 0;
        }

        p {
            margin: 0;
        }

        .dataTables_paginate {
            display: flex;
            justify-content: flex-end;

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
        #app {
            height: 100vh;
            overflow-y: auto;
            padding: 40px;
        }

    </style>

</head>
<body>
<div id="app">
    <div>
        <p class="fw6 f5 lh-copy">Тести</p>
        <p class="mt2 f7">
            @if($productsSynced)
                ✅ Товари синхронізовані
            @else
                ❌ Товари не синхронізовані
            @endif
        </p>
        <p class="mt2 f7">
            @if($categoriesSynced)
                ✅ Категорії синхронізовані
            @else
                ❌ Категорії не синхронізовані
            @endif
        </p>
    </div>
    @if(count($categories) > 0)
        <div class="section dn " style="margin-top: 50px">

            <p class="fw6 f5 lh-copy">Категорії</p>
            <div class="dib">
                <table id="categoryTable" style="border-collapse: collapse" class="f7 ba b--black mt3">
                    <thead>
                    <tr>
{{--                        <th width="100" class="fw4 ba b--black ph2 pv1 tc">Poster ID</th>--}}
{{--                        <th width="100" class="fw4 ba b--black ph2 pv1 tc">Salesbox ID</th>--}}
                        <th width="138" class="fw4 ba b--black ph2 pv1 tc">Name</th>
                        <th width="79" class="fw4 ba b--black ph2 pv1 tc">Існує в Salesbox?</th>
                        <th width="79" class="fw4 ba b--black ph2 pv1 tc">Існує в Poster?</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($categories as $category)
                        <tr>
{{--                            <td class="ba b--black pv1 ph2">{{$category['poster']['id']}}</td>--}}
{{--                            <td class="ba b--black pv1 ph2">{{$category['salesbox']['id']}}</td>--}}
                            <td class="ba b--black ph2 pv1">
                                <span style="width: 121px" class="truncate dib"
                                      title="{{ $category['name'] }}"> {{ $category['name'] }} </span>
                            </td>
                            <td class="ba b--black ph2 pv1 tc">
                                {{$category['salesbox']['created'] ? '✅': '❌'}}
                            </td>
                            <td class="ba b--black ph2 pv1 tc">
                                {{$category['poster']['created'] ? '✅': '❌'}}
                            </td>

                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>


            <form action="/poster-app/{{ $code }}/sync-categories" method="post" class="mt1">
                <button type="button" class="blue underline js-open pointer bn bg-transparent pa0 dim"
                        data-target="#option-1">Опції
                </button>

                <ul id="option-1" style="width: 300px;" class="list pl0 mb0 mt2 dn ">
                    <li>
                        <label title="Створює категорії в системі Salesbox" class="flex items-center">
                            <input name="create" type="checkbox" checked/>
                            <span class="ml1">Створення</span>
                        </label>
                    </li>
                    <li>
                        <label title="Оновлює категорії в системі Salesbox" class="flex items-center">
                            <input name="update" type="checkbox" checked/>
                            <span class="ml1">Оновлення</span>
                        </label>
                    </li>
                    <li>
                        <label title="Видаляє категорії з системи Salesbox" class="flex items-center">
                            <input name="delete" type="checkbox" checked/>
                            <span class="ml1">Видалення</span>
                        </label>
                    </li>
                </ul>
                <button type="submit" class="mt2 f7 bg-black white bn pv2 ph3 pointer dim db">
                    Синхронізувати
                </button>
                <div class="mt3">
                    <div style="font-size: 12px">Як працює синхронізація категорій?</div>
                    <ul class="list pl0 mt1 mb0" style="font-size: 10px">
                        <li>Якщо категорія існує в постері, але не існує в сейлсбоксі, то вона буде створена в сейлбоксі
                        </li>
                        <li>Якщо категорія існує в сейлбоксі, але не існує в постері, то вона буде видалена із сейлсбокса
                        </li>
                        <li>Якщо категорія існує в постері і в сейлбоксі, то вона ніяк не змінеться</li>
                    </ul>

                </div>

            </form>
        </div>
    @endif

    @if(count($products) > 0)
        <div class="section dn" style="margin-top: 75px">

            <p class="fw6 f5 lh-copy">Товари</p>
            <div class="dib">
                <table id="productTable" style="border-collapse: collapse" class="f7 ba b--black mt3">
                    <thead>
                    <tr>
{{--                        <th width="100" class="fw4 ba b--black ph2 pv1 tc">Poster ID</th>--}}
                        <th width="138" class="fw4 ba b--black ph2 pv1 tc">Name</th>
                        <th width="79" class="fw4 ba b--black ph2 pv1 tc">Існує в Salesbox?</th>
                        <th width="79" class="fw4 ba b--black ph2 pv1 tc">Існує в Poster?</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($products as $product)
                        <tr>
{{--                            <td class="ba b--black pv1 ph2">{{$product['poster']['id']}}</td>--}}
                            <td class="ba b--black pv1 ph2">
                                <span style="width: 121px" class="truncate dib"
                                      title="{{ $product['name'] }}">{{ $product['name'] }}</span>
                            </td>
                            <td class="ba b--black ph2 pv1 tc">
                                @if($product['salesbox']['created'])
                                    ✅
                                @else
                                    <button title="створити в сейлсбокс" class="bg-transparent bn" type="submit">❌
                                    </button>
                                @endif

                            </td>
                            <td class="ba b--black ph2 pv1 tc">
                                @if($product['poster']['created'])
                                    ✅
                                @else
                                    ❌
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>


            <form action="/poster-app/{{ $code }}/sync-products" method="post" class="mt1">
                <button type="button" class="blue underline js-open pointer bn bg-transparent pa0 dim"
                        data-target="#option-2">
                    Опції
                </button>
                <ul id="option-2" style="width: 200px;" class="list pl0 mb0 mt2 dn ">
                    <li>
                        <label class="flex items-center">
                            <input name="create" type="checkbox" checked/>
                            <span class="ml1">Створення</span>
                        </label>
                    </li>
                    <li>
                        <label class="flex items-center">
                            <input name="update" type="checkbox" checked/>
                            <span class="ml1">Оновлення</span>
                        </label>
                    </li>
                    <li>
                        <label class="flex items-center">
                            <input name="delete" type="checkbox" checked/>
                            <span class="ml1">Видалення</span>
                        </label>
                    </li>
                </ul>
                <button type="submit" class="mt2 f7 bg-black white bn pv2 ph3 pointer dim db">
                    Синхронізувати
                </button>
                <div class="mt3">
                    <div style="font-size: 12px">Як працює синхронізація товарів?</div>
                    <ul class="list pl0 mt1 mb0" style="font-size: 10px">
                        <li>Якщо товар існує в постері, але не існує в сейлсбоксі, то він буде створен в сейлбоксі</li>
                        <li>Якщо товар існує в сейлбоксі, але не існує в постері, то він буде видален із сейлсбокса</li>
                        <li>Якщо товар існує в постері і в сейлбоксі, то він буде оновлен(тільки ціна)</li>
                    </ul>

                </div>
            </form>
        </div>
    @endif
</div>
<script>


     window.addEventListener('load', function () {
         const $categoryTable = $('#categoryTable');
         $categoryTable.DataTable({
             searching: true,
             ordering: false,
             paging: true,
             autoWidth: false,
             info: false,
             bLengthChange: false
         });

         const $productTable = $('#productTable');
         $productTable.DataTable({
             searching: true,
             ordering: false,
             paging: true,
             autoWidth: false,
             info: false,
             bLengthChange: false
         });
         $('.section').removeClass('dn');
         $('.js-open').on('click', function () {

             const $this = $(this);
             const selector = $this.data('target');
             const $target = $(selector);
             if ($target.hasClass('dn')) {
                 $target.addClass('db');
                 $target.removeClass('dn');
             } else {
                 $target.addClass('dn');
                 $target.removeClass('db');
             }
         })
         top.postMessage({hideSpinner: true}, '*');
     }, false)
</script>


</body>
</html>

