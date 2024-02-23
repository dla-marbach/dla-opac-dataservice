<html>
<head>
    <title>DLA Marbach Datendienst</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css">
</head>
<body>
<div class="container mt-4">
    <div class="card">
        <div class="card-header text-center font-weight-bold">
            DLA Marbach Datendienst
        </div>
        <div class="card-body">
            <form name="frontendQuery" id="frontendQuery" method="post" action="{{url('v1/query')}}">
                @csrf
                <div class="form-group">
                    <label for="query">Query</label>
                    <input type="text" id="query" name="query" class="form-control" required="">
                </div>
                <div class="form-group">
                    <label for="formatQuery">Format</label>
                    <select class="form-select" name="formatQuery" id="formatQuery">
                        <option value="json">JSON</option>
                        <option value="csv">CSV</option>
                        <option value="ris">RIS</option>
                        <option value="mods">MODS</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary">Submit</button>
            </form>
            <form name="frontendIDs" id="frontendIDs" method="post" action="{{url('v1/id')}}">
                @csrf
                <div class="form-group">
                    <label for="identifier">Find data by identifier (comma separated)</label>
                    <input type="text" id="identifier" name="identifier" class="form-control" required="">
                </div>
                <div class="form-group">
                    <label for="formatId">Format</label>
                    <select class="form-select" name="formatId" id="formatId">
                        <option value="json">JSON</option>
                        <option value="csv">CSV</option>
                        <option value="ris">RIS</option>
                        <option value="mods">MODS</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary">Submit</button>
            </form>
        </div>
    </div>
</div>
</body>
</html>