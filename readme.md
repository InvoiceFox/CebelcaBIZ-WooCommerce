# Čebelca BIZ - WooCommerce dodatek

To je dodatek za WooCommerce (Wordpress), ki lahko na podlagi statusov naročil v spletni trgovini v Čebelco BIZ doda kupca, ustvari račun, doda plačilo, davčno potrdi račun in kreira dobavnico ki odpiše zalogo v skladišču. Opcij je več, izberete lahko le tiste, ki jih potrebujete.

## Status

Dodatek se aktivno uproablja na večih spletnih trgovinah in deluje.

## Namestitev na kratko

### 1. Prenesite plugin na svoj računalnik

Na strani https://github.com/InvoiceFox/CebelcaBIZ-WooCommerce si zgoraj desno kliknite **Code** in **Download ZIP**.

### 2. Naložite dodatek v vaš Wordpress

* V wordpress administraciji pojdite na "Plugins > Add New > Upload Plugin" ter izberite prenešeno ZIP datoteko.
ali
* Preko FTP ali file managerja naložite mapo **CebelcaBIZ-WooCommerce** v mapo **wordpress/wp-content/plugins/** na vašem strežniku.

### 3. Aktivirajte dodatek

* Na strani *Plugins > Installed plugins* poiščite **Cebelca BIZ** in kliknite **Activate**.

### 4. Nastavite dodatek

* Obiščite stran **WooCommerce > Settings > Integration > Cebalca BIZ**
* Vnesite **API key**, dobite ga na dnu strani Čebelca BIZ Strani **Nastavitve > Nastavitve dostopa**, potem ko aktivirate API.
* Na **On status change to "Complete"** izberite **Create invoice draft**. Ko bo naročilo šlo na status Zaključeno, se bo v Čebelco dodalo partnerja in ustvarilo osnutek računa. Osnutek lahko potem v sami Čebelci izdate, da postane Račun, ali če še testirate izbrišete.

Bolj obširna navodila najdete na: https://www.cebelca.biz/pomoc-integracija-woocommerce.html

## Deluje v povezavi s

www.cebelca.biz - program za idajanje računov

## Avtorj

Refaktor d.o.o.

## Dovoljenja

Dodatek je prosto dostopen vsem in je na voljo "kot je". Če potrebujete dodatne prilagoditve si jih skušajte urediti sami. Morebitni popravki in izboljšave so dobrodošli. Pošljite nam jih preko sistema Github.
