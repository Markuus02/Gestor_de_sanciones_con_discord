<?php include('db.php'); ?>
<form method="get">
    Buscar por usuario: <input type="text" name="usuario">
    <input type="submit" value="Buscar">
</form>

<?php
if (isset($_GET['usuario'])) {
    $usuario = $_GET['usuario'];
    $stmt = $conn->prepare("SELECT * FROM sanciones WHERE usuario = ?");
    $stmt->bind_param("s", $usuario);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        echo "<p>Usuario: {$row['usuario']} | Motivo: {$row['motivo']}</p>";
    }
}
?>
