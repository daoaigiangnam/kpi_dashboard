<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>KPI Dashboard Admin</title>

    <style>
        * { box-sizing: border-box; }

        body {
            margin: 0;
            font-family: Inter, Arial, sans-serif;
            background: #f6f9f7;
            color: #17231c;
        }

        .nav {
            width: 240px;
            position: fixed;
            inset: 0 auto 0 0;
            background: #123b2a;
            color: #fff;
            padding: 22px;
            overflow-y: auto;
            z-index: 100;
        }

        .brand {
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 28px;
        }

        .nav-toggle {
            display: none;
        }

        .nav a {
            display: block;
            color: #dcebe2;
            text-decoration: none;
            padding: 10px 12px;
            border-radius: 7px;
            margin: 4px 0;
        }

        .nav a:hover,
        .nav a.active {
            background: #2f8f5b;
            color: #fff;
        }

        .main {
            margin-left: 240px;
            padding: 28px;
            min-width: 0;
        }

        .top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 22px;
        }

        .card {
            background: #fff;
            border: 1px solid #e1e9e4;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 8px #123b2a0a;
            min-width: 0;
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 16px;
        }

        .table-wrap {
            width: 100%;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        .table {
            width: 100%;
            min-width: 680px;
            border-collapse: collapse;
        }

        .table th,
        .table td {
            padding: 12px;
            border-bottom: 1px solid #e8eee9;
            text-align: left;
            vertical-align: middle;
        }

        .table th {
            white-space: nowrap;
        }

        .table td:last-child {
            white-space: nowrap;
        }

        .actions {
            display: flex;
            gap: 6px;
            align-items: center;
            flex-wrap: wrap;
        }

        .btn {
            display: inline-block;
            border: 0;
            border-radius: 7px;
            padding: 9px 14px;
            text-decoration: none;
            cursor: pointer;
            background: #2e8b57;
            color: #fff;
            font-size: 14px;
        }

        .btn:hover {
            background: #267349;
        }

        .btn.gray {
            background: #64748b;
        }

        .btn.gray:hover {
            background: #475569;
        }

        .btn.red {
            background: #d9534f;
        }

        .btn.red:hover {
            background: #c43d39;
        }

        .form {
            max-width: 720px;
            width: 100%;
        }

        .input {
            width: 100%;
            padding: 10px;
            border: 1px solid #cfdad3;
            border-radius: 7px;
            margin-top: 5px;
            background: #fff;
        }

        .input:focus {
            outline: none;
            border-color: #2e8b57;
            box-shadow: 0 0 0 3px #2e8b571a;
        }

        .field {
            margin-bottom: 15px;
        }

        .alert {
            padding: 12px;
            background: #e8f6ed;
            color: #24613f;
            border: 1px solid #cce9d6;
            border-radius: 7px;
            margin-bottom: 15px;
        }

        .error {
            padding: 12px;
            background: #fcebea;
            color: #8f2f2c;
            border: 1px solid #f4c9c7;
            border-radius: 7px;
            margin-bottom: 15px;
        }

        .muted {
            color: #66736b;
            font-size: 13px;
        }

        @media (max-width: 900px) {
            .grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 700px) {
            .nav {
                position: fixed;
                left: 0;
                top: 0;
                bottom: auto;
                width: 100%;
                height: auto;
                padding: 14px 16px;
                overflow: visible;
            }

            .brand {
                margin: 0;
                display: inline-block;
                line-height: 40px;
            }

            .nav-toggle {
                display: block;
                position: absolute;
                right: 16px;
                top: 14px;
                width: 40px;
                height: 40px;
                border: 0;
                border-radius: 7px;
                background: #1d5b40;
                color: #fff;
                font-size: 22px;
                cursor: pointer;
            }

            .nav-toggle:hover {
                background: #2f8f5b;
            }

            .nav-links {
                display: none;
                padding-top: 10px;
            }

            .nav.open .nav-links {
                display: block;
            }

            .nav a {
                margin: 2px 0;
                padding: 9px 10px;
            }

            .nav form {
                margin-top: 12px !important;
                padding-bottom: 2px;
            }

            .main {
                margin-left: 0;
                padding: 78px 16px 24px;
            }

            .top {
                margin-bottom: 16px;
            }

            .top h1 {
                font-size: 28px !important;
                line-height: 1.2;
            }

            .grid {
                grid-template-columns: 1fr;
                gap: 12px;
            }

            .card {
                padding: 16px;
            }
        }
    </style>
</head>

<body>

<aside class="nav" id="adminNav">

    <div class="brand">KPI Dashboard</div>

    <button
        class="nav-toggle"
        type="button"
        aria-label="Toggle navigation"
        aria-expanded="false"
        onclick="toggleAdminNav()"
    >
        ☰
    </button>

    <div class="nav-links">

        <a href="{{ route('admin.dashboard') }}">
            Dashboard
        </a>

        @if(auth()->user()->hasPermission('users.view'))
            <a href="{{ route('admin.users.index') }}">
                Users
            </a>
        @endif

        @if(auth()->user()->hasPermission('groups.view'))
            <a href="{{ route('admin.groups.index') }}">
                User Groups
            </a>
        @endif

        @if(auth()->user()->hasPermission('job_titles.view'))
            <a href="{{ route('admin.job_titles.index') }}">
                Job Titles
            </a>
        @endif

        @if(auth()->user()->hasPermission('departments.view'))
            <a href="{{ route('admin.departments.index') }}">
                Departments
            </a>
        @endif

        @if(auth()->user()->hasPermission('units.view'))
            <a href="{{ route('admin.units.index') }}">
                Units
            </a>
        @endif

        <form
            method="post"
            action="{{ route('logout') }}"
            style="margin-top:25px"
        >
            @csrf

            <button class="btn gray" type="submit">
                Logout
            </button>
        </form>

    </div>

</aside>

<main class="main">

    <div class="top">
        <div>
            <h1 style="margin:0">
                @yield('title')
            </h1>

            <div class="muted">
                {{ auth()->user()->name }}
                ·
                {{ auth()->user()->group?->name }}
            </div>
        </div>
    </div>

    @if(session('success'))
        <div class="alert">
            {{ session('success') }}
        </div>
    @endif

    @if($errors->any())
        <div class="error">
            <ul>
                @foreach($errors->all() as $e)
                    <li>{{ $e }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @yield('content')

</main>

<script>
    function toggleAdminNav() {
        const nav = document.getElementById('adminNav');
        const button = nav.querySelector('.nav-toggle');
        const open = nav.classList.toggle('open');

        button.setAttribute(
            'aria-expanded',
            open ? 'true' : 'false'
        );
    }
</script>

</body>
</html>
