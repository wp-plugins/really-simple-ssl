��    ;      �      �      �  .   �  [   �  _   X  �   �  �   |  5  ;     q          �     �  ]   �     	  �   %  7   �     �  �   	  4   �	    �	  �  �  T   �  @     )   M     w     �     �  #   �     �  n   �  �   ^  5   �  5        P     T  ,   a     �     �  
   �     �  g   �     '     0  Q   E  i   �  ]     p   _  C   �  6     �  K      B     c   [  &   �    �  �   �  9   �  (   �     �     �  �     3     l   @  i   �  �     �   �  f  �                 $      @   z   T      �   �   �   <   �!  #   �!  �   �!  ;   �"  y  �"    L%  Y   l'  ?   �'  5   (     <(     V(     j(  /   �(     �(  f   �(  �   4)  ;   �)  <   *     H*     L*  /   ]*     �*      �*     �*     �*  r   �*     M+     Z+  R   y+  p   �+  Z   =,  �   �,  L   #-  :   p-  x  �-    $/  E   D0  f   �0  (   �0  %  1  �   @3  C   �3  )   44     ^4  (   e4   An SSL certificate was detected on your site.  Are you sure you have an SSL certifcate? Forcing ssl on a non-ssl site can break your site. Are you sure? Your visitors will keep going to a https site for a year after you turn this off. Because really simple ssl includes a mixed content fixer you do not have to worry about this list, but if you want to disable the mixed content fixer, you can find a list of possible issues here. Because your site is behind a loadbalancer and is_ssl() returns false, you should add the following line of code to your wp-config.php. Your wp-config.php could not be written automatically. By unchecking the 'auto replace mixed content' checkbox you can test if your site can run without this extra functionality. Uncheck, empty your cache when you use one, and go to the front end of your site. You should then check if you have mixed content errors, by clicking on the lock icon in the addres bar. Configuration Debug Detected mixed content Detected setup Editing of .htaccess is blocked in wp-config.php, so you're in control of the .htaccess file. Force SSL without detection HTTP Strict Transport Security was not set in your .htaccess. Do this only if your setup is fully working, and only when you do not plan to revert to http. HTTP Strict Transport Security was set in the .htaccess How to get an SSL certificate Https redirect was set in javascript because the htaccess redirect rule could not be verified. Set manually if you want to redirect in .htaccess. I'm sure I have an active SSL certificate, force it! In most sites, a lot of links are saved into the content, pluginoptions or even worse, in the theme. When you switch to ssl , these are still http, instead of https. To ensure a smooth transition, this plugin auto replaces all these links. If you see in the scan results that you have fixed most of these links, you can try to run your site without this replace script, which will give you a small performance advantage. If you do not have a lot of reported insecure links, you can try this. If you encounter mixed content warnings, just switch it back on. <br><br><b>How to check for mixed content?</b><br>Go to the the front end of your website, and click on the lock in your browser's address bar. When you have mixed content, this lock is not closed, or has a red cross over it. In the detected setup section you can see what we detected for your site.<br><br><b>SSL detection:</b> if it is possible to open a page on your site with https, it is assumed you have a valid ssl certificate. No guarantees can be given.<br><br><B>SSL redirect in .htaccess:</b> (Only show when ssl is detected) If possible, the redirect will take place in the .htaccess file. If this file is not available or not writable, javascript is used to enforce ssl. In the tab "detected mixed content" you can find a list of items with mixed content. Lightweight plugin without any setup to make your site ssl proof List of detected items with mixed content Log for debugging purposes Manage settings Mixed content detected  No SSL detected, but SSL is forced. No SSL detected. No SSL was detected. If you are just waiting for your ssl certificate to kick in you can dismiss this warning. No mixed content was detected. You could try to run your site without using the auto replace of insecure links, but check carefully.  Really Simple SSL has a conflict with another plugin. Really Simple SSL has detected a superfluous setting. SSL SSL settings SSl was detected and successfully activated! Save Scan SSL setup again Scan again Scanning... Send me a copy of these lines if you have any issues. The log will be erased when debug is set to false Settings Show me this setting The force http after leaving checkout in Woocommerce will create a redirect loop. The force rewrite titles option in Yoast SEO prevents Really Simple SSL plugin from fixing mixed content. The force ssl on checkout pages is not necessary anymore, and could cause unexpected results. The force ssl without detection option can be used when the ssl was not detected, but you are sure you have ssl. The mixed content scan is available when SSL is detected or forced. The scan searched for the following insecure links: %s This plugin tries to open a page within the plugin directory over https. If that fails, it is assumed that ssl is not availble. But as this may not cover all eventualities, it is possible to force the site over ssl anyway.<br><br> If you force your site over ssl without a valid ssl certificate, your site may break. In that case, remove the 'really simple ssl' rules from your .htaccess file (if present), and remove or rename the really simple ssl plugin. To secure your site with ssl, you need an SSL certificate. How you can get a certificate depends on your hosting provider, but can often be requested on the control panel of your website. If you are not sure what to do, you can contact your hosting provider. To view results here, enable the debug option in the settings tab. Try to add these rules at the bottom of your .htaccess. If it doesn't work, just remove them again. Turn HTTP Strict Transport Security on Using this option will prevent users from visiting your website over http for one year, so use this option with caution! HTTP Strict Transport Security (HSTS) is an opt-in security enhancement that is specified by a web application through the use of a special response header. Once a supported browser receives this header that browser will prevent any communications from being sent over HTTP to the specified domain and will instead send all communications over HTTPS. It also prevents HTTPS click through prompts on browsers.  We detected a definition of siteurl or homeurl in your wp-config.php, but the file is not writable. Because of this, we cannot set the siteurl to https. but that's ok, because the mixed content fixer is active. but the mixed content fix is not active. edit https redirect set in .htaccess Project-Id-Version: Really Simple SSL v2.1.18
PO-Revision-Date: 2015-08-28 20:15:54+0000
MIME-Version: 1.0
Content-Type: text/plain; charset=UTF-8
Content-Transfer-Encoding: 8bit
Plural-Forms: nplurals=2; plural=n != 1;
X-Generator: CSL v1.x Er is een SSL certificaat gedetecteerd op je site.  Weet je zeker dat je een SSL certificaat hebt? Het forceren van ssl op een niet-ssl site kan je site breken. Zeker? Je bezoekers zullen naar een https worden gestuurd gedurende een jaar nadat je dit hebt uitgezet.  Omdat really simple ssl een mixed content fixer ingebouwd heeft hoef je je geen zorgen te maken hierover, maar als je de mixed content fixer uit wilt schakelen kan je hier een lijst vinden met mogelijke problemen.  Omdat je site achter een loadbalancer zit en is_ssl() FALSE retourneert, moet je de volgende code toevoegen aan je wp-config.php. Je wp-config.php kon niet automatisch worden geschreven.  Door de 'auto replace mixed content' checkbox uit te vinken kan je testen of je site functioneert zonder deze extra functionaliteit. Verwijder het vinkje, leeg je cache als je die gebruikt, en ga naar de front end van je website. Controleer dan of je nog mixed content foutmeldingen hebt door te klikken op het slot icoontje in de adresbalk van je browser. 	 Configuratie Debug Gedetecteerde mixed content Gedetecteerde setup Het wijzigen van het .htaccess bestand is geblokkeerd in wp-config.php, dus jij regelt alles inzake het .htaccess bestand. Forceer SSL zonder detectie HTTP Strict Transport Security is niet ingesteld in je .htaccess. Doe dit alleen als je systeem volledig werkt, en alleen als je niet terug wilt naar http.  HTTP Strict Transport Security werd in .htaccess ingesteld.  Hoe kom je aan een SSL certificaat. Https redirect is ingesteld in javascript omdat de .htaccess redirect niet gecontroleerd kon worden. Stel deze handmatig in als je de redirect wilt instellen in .htaccess.  Ik weet zeker dat ik een SSL certificaat heb, forceer maar! In de meeste sites worden een hoop links opgeslagen in de inhoud, pluginopties, of nog erger in de themabestanden. Als je overgaat naar ssl zijn deze nog steeds http in plaats van https. Voor een soepele transitie vervangt deze plugin automatisch deze links. Als je in de scan resultaten ziet dat je de meeste van deze links al hebt aangepast kan je proberen de site te laten draaien zonder het vervang script (automatisch onveilige links vervangen uitschakelen). Dit geeft een klein performance voordeel. Als je weinig insecure links hebt kan je dit proberen. Als je mixed content meldingen krijgt kan je de optie weer inschakelen.  In de gedetecteerde setup kan je zien wat we hebben gedetecteerd voor jouw site.<br><br><br><b>SSL detectie:</b>Als het mogelijk is om een pagina te openen via https, wordt aangenomen dat je een geldig ssl certificaat op je domein hebt. Er kunnen geen garanties gegeven worden. <br><br><b>SSL redirect in .htaccess:</b> (alleen als ssl is gedetecteerd). Indien mogelijk wordt de redirect naar https gedaan in het .htaccess bestand. Als dit bestand niet beschikbaar is, of niet schrijfbaar, wordt javascript gebruikt om de redirect te regelen.  In the tab "gedetecteerde mixed content" kan je een lijst met mixed content items vinden. Lichtgewicht plugin zonder setup om je site ssl proof te maken. Lijst van items waarin mixed content gedetecteerd is. Log for debug doeleindes. Beheer instellingen Mixed content gedetecteerd  Geen ssl gedetecteerd, maar SSL is geforceerd.  Geen SSL gedetecteerd. Er is geen SSL gedetecteerd. Als je gewoon nog wacht op je certificaat kan je deze melding wegklikken. Er is geen mixed content gedetecteerd. Ja kan proberen je site te laten draaien zonder de auto replace functie, maar controleer het resultaat zorgvuldig.  Really Simple SSL heeft een conflict met een andere plugin. Really Simple SSL heeft een overbodige instelling gevonden.  SSL SSL instellingen SSL werd gedetecteerd en succesvol geactiveerd! Opslaan Nogmaal ssl configuratie scannen Nogmaals scannen Aan het scannen... Stuur me een kopie van deze regels als je problemen hebt. Het log wordt verwijderd als debug wordt uitgeschakeld.  Instellingen Laat me deze instelling zien.  De forceer http na het afrekenen in Woocommerce zal een redirect loop veroorzaken. De force rewrite titles optie in Yoast SEO zorgt ervoor dat Really Simple SSL geen mixed content kan vervangen.  De forceel ssl op afrekenpagina's is niet meer nodig, en kan onverwachte gevolgen hebben.  De forceer ssl zonder detectie optie kan gebruikt worden als er geen ssl werd gedetecteerd, maar als je er zeker van bent dat je ssl hebt. De mixed content scan is beschikbaar als SSL is gedetecteerd of geforceerd.  De scan heeft gezocht naar de volgende onveilige links: %s De plugin probeert een pagina via https te openen binnen de plugin directory. Als dat niet lukt, wordt er aangenomen dat ssl niet beschikbaar is. Omdat niet alle situaties kunnen worden voorzien, is het mogelijk om je site over https te forceren.<BR><br>Als je geen ssl certificaat hebt kan dit je site breken! In dat geval, volg de uninstall instructies op de plugin pagina.  Voor het beveiligen van je site met ssl heb je een SSL certificaat nodig. Hoe je een ssl certificaat kan verkrijgen verschilt per hosting provider, maar kan vaak aangevraagd worden via de control panel van je website. Als je niet weet hoe het moet, neem even contact op met je provider.  Om hier resultaten te zien, schakel debug in op de instellingen tab.  Probeer deze regels onderaan je .htaccess te plakken. Als dat niet werkt verwijder je ze gewoon weer.  Zet HTTP Strict Transport Security aan.  Het gebruik van deze optie voorkomt een jaar lang dat gebruikers je website via http kunnen bereiken, dus gebruik deze optie voorzichtig!.  HTTP Strict Transport Security (HSTS) is an opt-in security enhancement that is specified by a web application through the use of a special response header. Once a supported browser receives this header that browser will prevent any communications from being sent over HTTP to the specified domain and will instead send all communications over HTTPS. It also prevents HTTPS click through prompts on browsers.  We hebben een gedefinieerde siteurl of homeurl in je wp-config.php gedetecteerd, maar het bestand is niet schrijfbaar. Hierdoor kunnen we de siteurl niet naar https aanpassen. maar dat is niet erg, want de mixed content fixer is ingeschakeld.  maar de mixed content fix is niet actief. wijzig Https redirect ingesteld in de .htaccess 