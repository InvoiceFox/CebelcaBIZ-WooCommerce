# Čebelca BIZ dodatek za WooCommerce

Čebelca BIZ WooCommerce dodatek je WordPress "vtičnik" za e-trgovino, ki omogoča avtomatsko dodajanje kupcev, ustvarjanje računov, dodajanje plačil in kreiranje dobavnic glede na status naročil v spletni trgovini.

Dodatek je na voljo brezplačno na spletni strani Cebelca BIZ WooCommerce na Github-u in je odprtokodni projekt, uporabniki pa so vabljeni, da svoje izboljšave in popravke objavijo na Github-u.

## Status

Dodatek se že dlje časa uporablja v mnogih spletnih trgovinah.

## Navodila za namestitev

### Osnovni koraki namestitve

1. Prenesite najnovejšo različico dodatka iz [GitHub repozitorija](https://github.com/InvoiceFox/CebelcaBIZ-WooCommerce) ali iz [strani Čebelca BIZ](https://www.cebelca.biz/navodila/integracije/woocommerce/).
2. V WordPress nadzorni plošči pojdite na **Vtičniki > Dodaj novega > Naloži vtičnik**.
3. Izberite preneseno datoteko ZIP in kliknite **Namesti zdaj**.
4. Po namestitvi aktivirajte vtičnik.

### Preverjanje namestitve

Po aktivaciji vtičnika se bo v WooCommerce nastavitvah pojavila nova integracija. Za dostop do nastavitev:

1. Pojdite na **WooCommerce > Nastavitve > Integracije**.
2. Kliknite na **Čebelca BIZ** za dostop do nastavitev integracije.

## Osnovne nastavitve

### API ključ in povezava

1. Prijavite se v svoj Čebelca BIZ račun.
2. Pojdite na **Nastavitve > Dostop** in aktivirajte API.
3. Kopirajte ustvarjeni API ključ.
4. V WordPress nadzorni plošči pojdite na **WooCommerce > Nastavitve > Integracije > Čebelca BIZ**.
5. Vnesite API ključ v polje **Skrivni API ključ**.
6. Nastavite **Možne davčne stopnje** - vnesite vse davčne stopnje, ki jih uporabljate v vaši trgovini, ločene z vejicami (npr. "0, 5, 9.5, 22").
7. Nastavite **Pretvorbo načinov plačila** v obliki "Način plačila WooCommerce->Način plačila Čebelca;..." (npr. "PayPal->PayPal;Gotovina->Gotovina").
8. Shranite nastavitve.

### Nastavitve akcij ob spremembi statusa

Računi se kreirajo ob spremembi statusa naročila. Priporočljivo je, da se račun kreira le ob enem od statusov, da ne pride do podvajanja.

1. V nastavitvah Čebelca BIZ integracije poiščite razdelek **AKCIJE OB SPREMEBAH STATUSOV**.
2. Za vsak status naročila (Zadržano, V obdelavi, Zaključeno) izberite ustrezno akcijo:
   - **Brez akcije**: Ob tem statusu se ne izvede nobena akcija.
   - **Ustvari predračun**: Kreira se predračun v Čebelci BIZ.
   - **Ustvari predračun, pošlji PDF po e-pošti**: Kreira se predračun in pošlje po e-pošti stranki.
   - **Ustvari osnutek računa**: Kreira se osnutek računa v Čebelci BIZ.
   - **Ustvari in izdaj račun**: Kreira in izda se račun v Čebelci BIZ.
   - **Ustvari in izdaj račun, pošlji PDF po e-pošti**: Kreira in izda se račun ter pošlje po e-pošti stranki.
   - **Ustvari in izdaj račun, označi plačano, pošlji PDF**: Kreira in izda se račun, označi kot plačan ter pošlje po e-pošti.
   - **Ustvari in izdaj račun, označi plačano, odpiši zalogo, pošlji PDF**: Kreira in izda se račun, označi kot plačan, odpiše zalogo ter pošlje po e-pošti.

Za začetek je priporočljivo izbrati **Ustvari osnutek računa** pri statusu **Zaključeno**.

## Nastavitve davčne blagajne (fiskalizacija)

Če želite omogočiti davčno potrjevanje računov, sledite naslednjim korakom:

1. V nastavitvah Čebelca BIZ integracije poiščite razdelek **DAVČNA BLAGAJNA**.
2. Označite polje **Aktiviraj davčno potrjevanje**.
3. Nastavite **Načini plačila kjer dav. potrdi** - vnesite načine plačila, pri katerih naj se račun davčno potrdi, ločene z vejicami. Če vnesete "*", se bodo potrjevali vsi načini plačila.
4. Vnesite **ID prostora in blagajne** - številski podatek, ki ga najdete na strani Podatki & ID-ji v Čebelci BIZ.
5. Vnesite **Osebna davčna številka izdajatelja** - davčna številka osebe, ki račune izdaja.
6. Vnesite **Osebni naziv izdajatelja** - ime osebe, ki račune izdaja.

**Pomembno**: Preden vklopite avtomatsko davčno potrjevanje, se prepričajte, da imate WooCommerce in davčno blagajno pravilno nastavljeno in da se računi kreirajo pravilno (posebej bodite pozorni na davke in poštnino).

## Nastavitve zaloge

Če želite, da se zaloga avtomatsko odpisuje v Čebelci BIZ:

1. V nastavitvah Čebelca BIZ integracije poiščite razdelek **NASTAVITVE ZALOGE**.
2. Vnesite **ID Skladišča** - ID skladišča iz katerega naj se zaloga odpiše.
3. Prepričajte se, da so SKU kode v WooCommerce enake šifram artiklov v Čebelci BIZ.
4. V razdelku **DODATNE NASTAVITVE** označite polje **Naj se postavkam doda SKU**, če želite, da se SKU koda doda v opis postavke na računu.

## Dodatne nastavitve

V razdelku **DODATNE NASTAVITVE** lahko prilagodite še:

- **Opis izdelkov**: Dodajanje daljšega opisa artikla v račun.
- **Veljavnost predračuna**: Število dni veljavnosti predračuna.
- **Rok plačila pri stranki**: Privzeti rok plačila v dneh za nove stranke.
- **Besedilo o št. naročila**: Besedilo, ki se prikaže pred številko naročila na računu.
- **Besedilo za delni seštevek**: Besedilo za prikaz delnega seštevka pred dodajanjem stroškov dostave.
- **Zaokrožanje**: Nastavitve zaokroževanja za neto cene, davčne stopnje in zneske dostave.

## Beleženje dogodkov (debug)

Za lažje odkrivanje napak lahko aktivirate beleženje dogodkov:

1. V razdelku **OSNOVNE NASTAVITVE** označite polje **Aktiviraj beleženje dogodkov**.
2. Dnevnik se nahaja v: `wp-content/cebelcabiz-debug.log`.
3. Dnevnik lahko pregledate, počistite ali prenesete v razdelku **Debug Log** na dnu strani z nastavitvami.

## Navodila za razvijalce

### Struktura projekta

Glavne datoteke in mape v projektu:

- `cebelcabiz.php`: Glavna datoteka vtičnika.
- `includes/`: Mapa z razredi za integracijo in pregledovalnik dnevnika.
  - `class-wc-integration-cebelcabiz.php`: Razred za integracijo z WooCommerce.
  - `class-wc-cebelcabiz-log-viewer.php`: Razred za pregledovanje dnevnika.
- `lib/`: Mapa s knjižnicami za API.
  - `invfoxapi.php`: Knjižnica za komunikacijo s Čebelca BIZ API-jem.
  - `strpcapi.php`: Pomožna knjižnica.
- `assets/`: Mapa s CSS in JavaScript datotekami.

### Pakiranje vtičnika

Za pakiranje vtičnika v ZIP datoteko uporabite priloženo skripto `package-plugin.sh`:

1. Odprite terminal in se pomaknite v mapo projekta.
2. Zaženite skripto z ukazom:
   ```bash
   ./package-plugin.sh
   ```
3. Skripta bo ustvarila mapo `cebelcabiz-woocommerce` in vanjo kopirala vse potrebne datoteke.
4. Nato bo ustvarila ZIP datoteko `cebelcabiz-woocommerce.zip`, ki jo lahko naložite v WordPress.

### Razvoj in prispevanje

Če želite prispevati k razvoju vtičnika:

1. Ustvarite fork repozitorija na GitHub-u.
2. Naredite svoje spremembe v ločeni veji.
3. Pošljite pull request z opisom sprememb.

## Dnevnik sprememb

**28.04.2024** V nastavitve se lahko vnese načine plačila na Woocommerce in njihove ekvivalente na Čebelca.biz in plugin bo dodal pravi način plačila. Če se DDV ne uspe preračunati (primerne stopnje ni v nastavitvah) se račun ne bo izdal oz. davčno potrdil preko API-ja

**01.03.2023** besedilo variacije izdelka se prenese na naziv postavke

**15.03.2023** Popust se sedaj izračuna iz "item totals" in cene izdelka, bodite pozorni, enota se prenese če je v _meta "unit_of_measurment" pri produktu, popravek buga pri fiskalizaciji - id lokacije se ni shranila v nastavitve

## Dovoljenja

Dodatek je prosto dostopen vsem in je na voljo "kot je". Če potrebujete dodatne prilagoditve si jih skušajte urediti sami. Morebitni popravki in izboljšave so dobrodošli. Pošljite nam jih preko sistema Github.

## Avtor

Janko Metelko za Refaktor d.o.o.
