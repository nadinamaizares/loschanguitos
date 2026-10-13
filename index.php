<?php
/**
 * Pagina de entrada del dominio.
 *
 * Si alguien entra a https://tudominio/ sin mas, lo mandamos al login del
 * sistema. Asi no tienen que escribir /frontend/login.php a mano.
 */
header('Location: frontend/login.php');
exit;