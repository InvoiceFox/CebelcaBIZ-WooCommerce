# Navodila za namestitev

_... v delu ..._

Da plugin namestite v vaš Woocommerce (Wordpres) mapo woocommerce-invoicefox naložite na vaš strežnik v woocommerce mapo wp-content/plugins.

Potem obiščete Wordpress admin in pod Plugins aktivirate plugin InvoiceFox.

# Priporočilo 

Najprej priporočamo da plugin nastavite tako, da ta kreira partnerje in osnutke v Čebelci. Vi pa potem v čebelci račun vsebinsko preverite in davčno potrdite.
Posebej pozorni bodite na DDV in poštnine, ki so lahko vezani na dodatne plugine v vašem Woocommerce, ki jih naš plugin še ne pozna.

Šele ko nekaj časa uspešno kreirate osnutke in jih pred davčno potrditvijo preverite vam priporočamo, da račune avtomatsko tudi davčno potrjujete.

# API ključ

Večino nastavitev lahko pustite kot so. Izpolniti morate predvsem API ključ. Api ključ najdete, ko se priavite v Čebelco in obiščete stran Nastavitve in 
potem Nastavitve dostopa. Na dnu strani boste našli tipko "Aktiviraj API". Da API aktivirate morate vnesti geslo, s katerim se prijavite v Čebelco.

Ko je API aktiviran vam pokaže API ključ, ki ga kopirate v nastavitve Woocommerce Invoicefox pod **API key**.

# Avtomatsko kreiranje osnutkov

Plugin se običajno uporablja tako, da prehode na določene statuse naročila vežete na določene akcije. Npr. ko naročilo dobi status **completed** se lahko kreira partner in osnutek računa v Čebelci. Da to deluje morate polega API ključa nastaviti še polje **On order status change to "Completed"** na vrednost **create invoice draft**.

# Avtomatsko izdajanje računov

_... kasneje ..._

# Fiskalizacija

To opcijo aktivirajte šele, ko boste prepričani, da se računi v Čebelco prenesejo pravilno. Ko je račun enkrat izdan in davčno potrjen, se ga ne da več spremeniti. Lahko ga daste edino še v dobropis, kar je dodaten dokument v minus računa.

Preden lahko aktivirate davčno potrjevanje računov morate vnesti ta 4 polja:

**Fiscalisation for Slovenia** - obkljukate
**Fiscalisation ID of location** - notranji ID prostora v Čebelca pri nastavitvah davčne blagajne. Če je to vaš prvi prostor bo njegov ID enak 1.
**Fiscalisation operator's tax id** - osebna davčna številka osebe, ki izdaja račune
**Fiscalisation operator's name** - kratek naziv osebe, ki izdaja računa 
