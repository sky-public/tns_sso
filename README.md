$noSessionCheck = true; in die TANSS config.inc.php eintragen.

Dateien einfach in ein Unterverzeichnis kopieren, z.B. /sso, die config.inc.php mit TANSS URL und Entra Anwendungsdaten (Directory ID, App ID, App Secret) ausstatten, fertig.
Die Datenbankanbindung zieht das Modul aus der TANSS-Config.

Der Parameter 'show\_welcome\_screen' => true blendet auf Wunsch eine SSO Welcome Seite ein, ich habe diese aber deaktiviert, da unnötig.
Vereinzelt kommt es beim ersten SSO Login zu einem Fehler, der aber ab dem zweiten Versuch nicht wieder auftritt. Wer da Zeit und Lust hat, mag es gerne debuggen.

Die Nutzung des Moduls erfolgt dann über Aufruf des gewählten Unterverzeichnises, also z.B. www.mein-tanns.de/sso



Wer den SSO-Button auf der Startseite haben möchte, kann auch die sso-inject.js nutzen. 

Dafür in einer vorhandenen .js Datei im ajax-Ordner die Datei via script nachladen:





that snippet shall only load if the URL is "index.php?login=1"



4:36 PM

I'll update the loader script to only load sso-inject.js when the URL matches that condition:





Adding URL check to only load sso-inject.js when URL contains index.php?login=1

+25

-19

loader.js



Presented file

Perfect! Now the loader script will only load sso-inject.js when the URL contains index.php?login=1. The script checks the current URL and only creates and appends the script tag if the condition is met.



Loader

JS 

console says sso-inject.js loaded successfully



5:23 PM

Great! That means the loader is working correctly and sso-inject.js has been successfully loaded on your login page.



Now the SSO button should appear on the page. Check if you can see the "Anmelden mit SSO" button next to the original "Anmelden" button.



If the button isn't appearing, it could be because:



The div.login-form-row element hasn't loaded yet when the script runs

The button selector doesn't match exactly

You can check the browser console for any errors. If you need to debug further, let me know what you're seeing (or not seeing) and I can help adjust the script!



The button does not appear



5:23 PM

Let's debug this. Can you open the browser console and run these commands to check what's happening:



javascript

// Check if the login form row exists

console.log('Login form row:', document.querySelector('div.login-form-row'));



// Check if the button exists

console.log('Original button:', document.querySelector('button.tns-button.lt-full-width\[type="submit"]'));



// Check all buttons on the page

console.log('All buttons:', document.querySelectorAll('button'));

This will help us see:



Whether the div.login-form-row element exists

Whether the original button can be found with that selector

What buttons are actually on the page

Once you run these and share the results, I can adjust the sso-inject.js script to properly target the elements on your page.



Alternatively, you could inspect the page HTML and share the actual structure of the login form, and I'll update the script accordingly.



tns\_main.js:2 WebSocket: no URL given

fav\_ajax.js:108 sso-inject.js loaded successfully

// Check if the login form row exists

console.log('Login form row:', document.querySelector('div.login-form-row'));

// Check if the button exists

console.log('Original button:', document.querySelector('button.tns-button.lt-full-width\[type="submit"]'));

// Check all buttons on the page

console.log('All buttons:', document.querySelectorAll('button'));

VM357:2 Login form row: <div class="login-form-row"><label for=​"login-form-input-username" class=​"login-form-label">​Benutzername​</label>​<div class=​"login-form-input">​…​</div>​</div>​

VM357:5 Original button: <button class="tns-button lt-full-width" type=​"submit">​…​</button>​flex

VM357:8 All buttons: NodeList \[button.tns-button.lt-full-width]

undefined



Mehr anzeigen

5:23 PM

Good! Both elements exist. The issue is likely timing - the sso-inject.js script is loading and running before the DOM is fully ready. Let me update it to handle this better:





Updating sso-inject.js to ensure it runs after DOM is ready and add better timing handling

+54

-28

sso-button.js



Presented file

I've updated the script with better timing handling:



Multiple timing strategies - Tries to add the button immediately if DOM is ready, on DOMContentLoaded if not, and also with delayed timeouts

Duplicate prevention - Adds a class to check if the button already exists

Console logging - Shows what's happening for easier debugging

Replace your current sso-inject.js with this updated version and reload the page. You should now see console messages telling you whether the button was added successfully or what went wrong.



Sso button

JS 

nice, works. can you add a spacer between the 2 buttons?



5:24 PM

I'll add a spacer between the two buttons:





Adding a spacer div between the original button and the SSO button

+17

-9

sso-button.js



Presented file

Done! I've added a 10px spacer between the two buttons. You can adjust the height by changing the spacer.style.height = '10px'; value to whatever spacing you prefer (e.g., '15px', '20px', etc.).



Sso button

JS 

can you also add a small 4 squares microsoft logo to the button?



5:25 PM

I'll add a Microsoft logo to the SSO button:





Adding Microsoft 4-square logo to the SSO button

+20

-7

sso-button.js



Presented file

Perfect! I've added the Microsoft 4-square logo to the button. The logo uses the official Microsoft colors:



Red (top-left)

Green (top-right)

Blue (bottom-left)

Yellow (bottom-right)

The logo is 16x16 pixels with 8px spacing to the right of the text. You can adjust the size by changing the width and height values in the SVG, or adjust the spacing by changing the margin-right value.





// Dynamically load the sso-inject.js script only on login page

(function() {

&nbsp;   // Check if the current URL is index.php?login=1

&nbsp;   const currentURL = window.location.href;

&nbsp;   const isLoginPage = currentURL.includes('index.php?login=1');

&nbsp;   

&nbsp;   if (isLoginPage) {

&nbsp;       const script = document.createElement('script');

&nbsp;       script.src = 'sso-inject.js';

&nbsp;       script.type = 'text/javascript';

&nbsp;       

&nbsp;       // Optional: Add error handling

&nbsp;       script.onerror = function() {

&nbsp;           console.error('Failed to load sso-inject.js');

&nbsp;       };

&nbsp;       

&nbsp;       // Optional: Add load confirmation

&nbsp;       script.onload = function() {

&nbsp;           console.log('sso-inject.js loaded successfully');

&nbsp;       };

&nbsp;       

&nbsp;       // Append the script to the document head or body

&nbsp;       document.head.appendChild(script);

&nbsp;   }

})();



