$noSessionCheck = true; in die TANSS config.inc.php eintragen.

Dateien einfach in ein Unterverzeichnis kopieren, z.B. /sso, die config.inc.php mit TANSS URL und Entra Anwendungsdaten (Directory ID, App ID, App Secret) ausstatten, fertig.
Die Datenbankanbindung zieht das Modul aus der TANSS-Config.

Der Parameter 'show\_welcome\_screen' => true blendet auf Wunsch eine SSO Welcome Seite ein, ich habe diese aber deaktiviert, da unnötig.
Vereinzelt kommt es beim ersten SSO Login zu einem Fehler, der aber ab dem zweiten Versuch nicht wieder auftritt. Wer da Zeit und Lust hat, mag es gerne debuggen.

Die Nutzung des Moduls erfolgt dann über Aufruf des gewählten Unterverzeichnises, also z.B. www.mein-tanns.de/sso



Wer den SSO-Button auf der Startseite haben möchte, kann auch die sso-inject.js nutzen. 

Dafür in einer vorhandenen .js Datei im ajax-Ordner die Datei via script nachladen:



```javascript
// Dynamically load the sso-inject.js script only on login page

(function() {
   // Check if the current URL is index.php?login=1
   const currentURL = window.location.href;
   const isLoginPage = currentURL.includes('index.php?login=1');

   if (isLoginPage) {
       const script = document.createElement('script');
       script.src = 'sso-inject.js';
       script.type = 'text/javascript';
       
       // Optional: Add error handling
       script.onerror = function() {
           console.error('Failed to load sso-inject.js');
       };
       
       // Optional: Add load confirmation
       script.onload = function() {
           console.log('sso-inject.js loaded successfully');
       };
       
       // Append the script to the document head or body
       document.head.appendChild(script);
   }
})();
```
