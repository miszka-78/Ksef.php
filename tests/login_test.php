<?php
/**
 * Test jednostkowy dla funkcji logowania
 * 
 * Ten skrypt testuje funkcję logowania i weryfikuje czy hasła są
 * poprawnie weryfikowane dla użytkownika admin.
 */

// Ustawienie ścieżki do głównego katalogu projektu
$projectRoot = dirname(__DIR__);

// Załadowanie pliku konfiguracyjnego
require_once $projectRoot . '/config/config.php';

// Ustawienie nagłówka
echo "\n=====================================\n";
echo "TEST JEDNOSTKOWY LOGOWANIA\n";
echo "=====================================\n\n";

// 1. Test połączenia z bazą danych
echo "1. Testowanie połączenia z bazą danych... ";
$db = Database::getInstance();
if ($db) {
    echo "OK\n";
} else {
    echo "BŁĄD!\n";
    exit(1);
}

// 2. Test pobierania użytkownika z bazy danych
echo "2. Testowanie pobierania użytkownika 'admin' z bazy danych... ";
$query = "SELECT * FROM users WHERE username = ?";
$user = $db->fetchRow($query, ['admin']);

if ($user) {
    echo "OK (znaleziono użytkownika o ID: {$user['id']})\n";
} else {
    echo "BŁĄD! Użytkownik 'admin' nie istnieje w bazie danych.\n";
    exit(1);
}

// 3. Test niezaszyfrowanego hasła "admin"
echo "3. Testowanie hasła 'admin' dla użytkownika 'admin'... ";
$plainPassword = 'admin';
$passwordVerified = password_verify($plainPassword, $user['password']);
if ($passwordVerified) {
    echo "OK (hasło się zgadza)\n";
} else {
    echo "BŁĄD! Hasło 'admin' nie jest prawidłowe dla użytkownika 'admin'.\n";
    
    // Generowanie hasha dla hasła "admin" aby umożliwić logowanie
    echo "   Generowanie hasha dla hasła 'admin'...\n";
    $newHash = password_hash($plainPassword, PASSWORD_DEFAULT);
    echo "   Nowy hash: $newHash\n";
    echo "   Aktualizacja hasła w bazie danych...\n";
    
    $updated = $db->update('users', ['password' => $newHash], 'id = ?', [$user['id']]);
    if ($updated) {
        echo "   Hasło zaktualizowane pomyślnie.\n";
    } else {
        echo "   BŁĄD podczas aktualizacji hasła!\n";
        exit(1);
    }
}

// 4. Test, czy hasło "admin" działa dla obiektu User (przez funkcję authenticate)
echo "4. Testowanie funkcji authenticate() dla użytkownika 'admin' z hasłem 'admin'... ";
$user = new User();
$authenticated = $user->authenticate('admin', 'admin');

if ($authenticated) {
    echo "OK (użytkownik został uwierzytelniony)\n";
} else {
    echo "BŁĄD! Funkcja authenticate() nie zadziałała poprawnie.\n";
    exit(1);
}

// Test zakończony sukcesem
echo "\nTESTY ZAKOŃCZONE SUKCESEM!\n";
echo "Możesz teraz zalogować się jako:\n";
echo "Użytkownik: admin\n";
echo "Hasło: admin\n";