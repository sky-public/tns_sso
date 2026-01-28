$noSessionCheck = true; in die TANSS config.inc.php eintragen.

Dateien einfach in ein Unterverzeichnis kopieren, z.B. /sso, die config.inc.php mit TANSS URL und Entra Anwendungsdaten (Directory ID, App ID, App Secret) ausstatten, fertig.
Die Datenbankanbindung zieht das Modul aus der TANSS-Config.
Der Parameter 'show_welcome_screen' => true blendet auf Wunsch eine SSO Welcome Seite ein, ich habe diese aber deaktiviert, da unnötig.
Vereinzelt kommt es beim ersten SSO Login zu einem Fehler, der aber ab dem zweiten Versuch nicht wieder auftritt. Wer da Zeit und Lust hat, mag es gerne debuggen.
Die Nutzung des Moduls erfolgt dann über Aufruf des gewählten Unterverzeichnises, also z.B. www.mein-tanns.de/sso
