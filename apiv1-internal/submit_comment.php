<?php
session_start();

header("Content-Type: application/json");

$response = array();

$rateLimit = 2.5;

function isRateLimited($rateLimitFile, $rateLimit) {
    if (!file_exists($rateLimitFile) || (time() - filemtime($rateLimitFile)) > $rateLimit) {
        touch($rateLimitFile);
        return false;
    } else {
        return true;
    }
}

$rateLimitFile = sys_get_temp_dir() . '/' . 'ratelimit_comments.txt';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $commentContent = $_POST['commentContent'];
    $postID = $_POST['postID'];
    $username = $_POST['username'];

    include("../important/db.php");

    $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

    if ($conn->connect_error) {
        $response['status'] = 'error';
        $response['message'] = "Connection failed: " . $conn->connect_error;
        echo json_encode($response);
        exit();
    }

    $getPostOwnerQuery = "SELECT username FROM posts WHERE id = ?";
    $stmt = $conn->prepare($getPostOwnerQuery);
    $stmt->bind_param("i", $postID);
    $stmt->execute();
    $result = $stmt->get_result();
    $postOwnerData = $result->fetch_assoc();
    $postOwnerUsername = $postOwnerData['username'];

    if (!$postOwnerUsername) {
        $response['status'] = 'error';
        $response['message'] = 'Failed to retrieve post owner\'s username';
        echo json_encode($response);
        exit();
    }

    if (empty($commentContent)) {
        $response['status'] = 'error';
        $response['message'] = 'Comment content cannot be blank';
        echo json_encode($response);
        exit();
    }

    if (trim($commentContent) === '') {
        $response['status'] = 'error';
        $response['message'] = 'Comment cannot consist only of spaces';
        echo json_encode($response);
        exit();
    }

    $query = "INSERT INTO comments (post_id, username, comment_content) VALUES (?, ?, ?)";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("iss", $postID, $username, $commentContent);

    if ($stmt->execute()) {
        $postId = $conn->insert_id;

        function extractMentions($content) {
            preg_match_all('/\+([a-zA-Z0-9_]+)/', $content, $matches);
            return $matches[1];
        }    

        $mentions = extractMentions($commentContent);

        foreach ($mentions as $mentionedUser) {
            $mentionContent = "Someone commented on your post!";
            $insertMentionQuery = "INSERT INTO notifications (recipient, content, created_at, read_status, sender, post_id) VALUES (?, ?, NOW(), 0, ?, ?)";
            $stmt = $conn->prepare($insertMentionQuery);
            $stmt->bind_param("sssi", $mentionedUser, $mentionContent, $username,  $_POST['postID']);
            $stmt->execute();
        }


        if ($username !== $postOwnerUsername) {
            $ownerNotificationContent = "$username commented on your post!";
            $insertOwnerNotificationQuery = "INSERT INTO notifications (recipient, content, created_at, read_status, sender, post_id) VALUES (?, ?, NOW(), 0, ?, ?)";
            $stmt = $conn->prepare($insertOwnerNotificationQuery);
            $stmt->bind_param("sssi", $postOwnerUsername, $ownerNotificationContent, $username, $postID);
            $stmt->execute();
        }

        $response['status'] = 'success';
        $response['message'] = 'Comment successfully added';
        $response['commentContent'] = $commentContent;
        $response['postID'] = $postId;
        $response['username'] = $username;
    } else {
        $response['status'] = 'error';
        $response['message'] = "Error: " . $conn->error;
    }

    $stmt->close();
    $conn->close();
} else {
    $response['status'] = 'error';
    $response['message'] = 'Invalid request method';
}

echo json_encode($response);
?>
