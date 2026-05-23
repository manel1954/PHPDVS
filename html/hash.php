<?php
$hash_a_encontrar = "$2y$10$3j2vcfaGFxWlmPB\/jmJj3O1PwKfwlB5AKw8x9HNbcGd4lh.0vkqs";
$diccionario = ["123456", "admin", "password", "hola", "qwerty", "contraseña"];

foreach ($diccionario as $palabra) {
    if (md5($palabra) === $hash_a_encontrar) {
        echo "Contraseña encontrada: $palabra";
        exit;
    }
}
echo "No se encontró ninguna contraseña.";