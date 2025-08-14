<?php include('db.php'); ?>
<form method="post">
    Usuario: <input type="text" name="usuario"><br>
    Motivo: <input type="text" name="motivo"><br>
    <input type="submit" name="enviar" value="Registrar">
</form>

<?php
if (isset($_POST['enviar'])) {
    $usuario = $_POST['usuario'];
    $motivo = $_POST['motivo'];
    $stmt = $conn->prepare("INSERT INTO sanciones (usuario, motivo) VALUES (?, ?)");
    $stmt->bind_param("ss", $usuario, $motivo);
    $stmt->execute();
    echo "Sanción registrada.";
}
?>
