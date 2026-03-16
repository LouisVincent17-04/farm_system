<!DOCTYPE html>
<html>
<body>

<h2>PHP → Python AI Demo</h2>

<form method="post">
    <input type="text" name="message" placeholder="Enter text">
    <button type="submit">Send</button>
</form>

<?php
if(isset($_POST['message'])){

    $data = [
        "text" => $_POST['message']
    ];

    $options = [
        "http" => [
            "header"  => "Content-Type: application/json",
            "method"  => "POST",
            "content" => json_encode($data)
        ]
    ];

    $context = stream_context_create($options);

    $result = file_get_contents(
        "http://127.0.0.1:8000/hello",
        false,
        $context
    );

    $response = json_decode($result, true);

    echo "<h3>".$response["result"]."</h3>";
}
?>

</body>
</html>