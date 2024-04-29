<?php

use Gibbon\Tables\DataTable;
use Gibbon\Domain\DataSet;

// Module includes
require_once __DIR__ . '/moduleFunctions.php';

if (isActionAccessible($guid, $connection2, '/modules/Exam Analysis/meanScoreOverYears.php')==false) {
	// Access denied
	$page->addError(__('You do not have access to this action.'));
} else {
    echo '
    <!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ELM Secondary: Overall Mean Score Trend</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: Arial, sans-serif;
        }

        .container {
            max-width: 100%;
            padding: 20px;
        }

        h1 {
            text-align: center;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            text-align: center;
            padding: 10px;
        }

        iframe {
            width: 100%;
            height: 400px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>ELM Lower Primary: Overall Mean Score Trend</h1>
        <table>
            <tbody>
                <tr>
                    <td><iframe
                    width="600"
                    height="400"
                    seamless
                    frameBorder="0"
                    scrolling="no"
                    src="https://new-h.elmischools.com/superset/explore/p/3LQEmn3PMBe/?standalone=1&height=400"
                  >
                  </iframe></td>
                </tr>
            </tbody>
        </table>
    </div>
    <div class="container">
    <h1>ELM Lower: Form Groups</h1>
    <table>
        <tbody>

            <tr>
            <iframe
  width="600"
  height="400"
  seamless
  frameBorder="0"
  scrolling="no"
  src="https://new-h.elmischools.com/superset/explore/p/q3ZDnyOKMwK/?standalone=1&height=400"
>
</iframe>
            </tr>
        </tbody>
    </table>
</div>
</body>
</html>

    ';

}	
?>