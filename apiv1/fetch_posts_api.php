<?php

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");

function getPostsFromDatabase($username = null) {
    include("../important/db.php");
    $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    $query = "SELECT * FROM posts";
    if ($username !== null) {
        $query .= " WHERE username = '$username'";
    }
    $query .= " ORDER BY created_at DESC";

    $result = $conn->query($query);
    $posts = array();

    if ($result->num_rows > 0) {
        while ($post = $result->fetch_assoc()) {
            if (!empty($post['image_url'])) {
                $image_url = str_replace('..', '', $siteurl) . str_replace('..', '', $post['image_url']);
            } else {
                $image_url = null;
            }
            $posts[] = array(
                'id' => $post['id'],
                'username' => $post['username'],
                'content' => htmlspecialchars($post['content']),
                'image_url' => $image_url,
                'post_link' => $post['post_link'],
                'created_at' => $post['created_at']
            );
        }
    }

    $conn->close();

    return $posts;
}

if (isset($_GET['username'])) {
    $username = $_GET['username'];
    $postsData = getPostsFromDatabase($username);
} else {
    $postsData = getPostsFromDatabase();
}

echo json_encode($postsData);
?>
