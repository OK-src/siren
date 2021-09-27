<?php
	//connection
	require_once("functions.php");
	$conn = connect();
	session_start();
	
	if(isset($_POST['url'])){
		$url = $_POST['url'];
		$_SESSION['url'] = $url;
	}
?>

<!DOCTYPE html>
<html lang="en">
	<head>
		<meta charset="UTF-8">
		<meta http-equiv="X-UA-Compatible" content="IE=edge">
		<meta name="viewport" content="width=device-width, initial-scale=1.0">
		<link rel="stylesheet" type="text/css" href="styles/main.css">
		<title>Siren | add site</title>
	</head>
	<body>
		<form method="post">
			<input type="text" name="url" placeholder="insert an URL" value="<?php echo $_SESSION['url']; ?>">
			<input type="submit">
		</form>
	</body>
</html>

<?php
	if(isset($_POST['url'])){
		$url = $_POST['url'];
		
		$domain = domain($url);
		$domain = $domain[0];
		
		//check if the domain exist
		if(validateUrl($domain)){
			//check if the site is already present in the database
			$query = mysqli_query($conn, "SELECT id FROM sites WHERE url = '$domain'");
			$fromObj = mysqli_fetch_assoc($query);
			if(@count($fromObj) == 0){
				//add the site in the database
				$sql = "INSERT INTO sites (url) VALUES ('$domain')";
				
				if ($conn->query($sql) === TRUE) {
					echo "$domain has been added to our database<br>";
				} else {
					echo "Error: " . $sql . "<br>" . $conn->error;
				}
			} else {
				echo "$domain is already in our database<br>";
			}
		} else {
			echo "this site does not exist or does not have an index<br>";
		}
	}
?>
