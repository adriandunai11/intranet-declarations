# Nyilatkozati modul - rendszerleírás és oktatási anyag

## Mire való a modul?

A nyilatkozati modul arra szolgál, hogy a munkavállalók online töltsék ki és küldjék be a belépéshez, éves adóügyekhez és későbbi adatváltozásokhoz szükséges nyilatkozatokat.

A kitöltés védett public linken történik. A link önmagában nem elég: a kitöltőnek az e-mailben szereplő ANTRA azonosítót is meg kell adnia. A rendszer a beküldött adatokról naplózott bizonyítékot tárol, például a kitöltő e-mail címét, intranet felhasználóját, IP címét, böngészőadatait és az adatlenyomatot.

## Alapfogalmak

- **Személy**: a nyilatkozati modulban kezelt munkavállalói adatlap.
- **Jogviszony / beléptetési folyamat**: az adott céges kapcsolódás, amelyhez nyilatkozatcsomag indítható.
- **Nyilatkozatcsomag**: egy vagy több nyilatkozat együttese, amelyet egy linken keresztül tölt ki a dolgozó.
- **Nyilatkozat**: egy konkrét űrlap, például bankszámlaszám, személyes adatok, gyermek után járó pótszabadság vagy adóügyi nyilatkozat.
- **Meghívó link**: e-mailben kiküldött kitöltési link. Új link küldésekor a korábbi aktív linkek érvényüket vesztik.
- **Nyilatkozati év**: az az év, amelyhez a csomag tartozik. Nem adóügyi nyilatkozatnál ez inkább adminisztratív csoportosítás, nem tiltja a későbbi új beküldést.

## Szerepkörök

### Toborzó

- Új belépőnél a toborzó indítja az első nyilatkozatcsomagot.
- Az alap beléptetési csomag nem tartalmaz automatikusan adóügyi nyilatkozatokat.
- Ha már belépéskor biztosan tudható, hogy egy adóügyi nyilatkozat szükséges, a toborzó vagy munkaügy külön beteheti a csomagba.
- A kitöltési felkérő e-mailben szerepel az ANTRA azonosító.

### Munkaügy / bérszámfejtés

- Ellenőrzi a beérkezett nyilatkozatokat a jogosultsági körének megfelelően.
- Elfogadhatja a nyilatkozatot, vagy javításra visszaküldheti.
- Későbbi éves vagy adatváltozási nyilatkozatcsomagot is kiküldhet.
- Elfogadás után lezárja a csomagot, hogy új csomag indítható legyen.
- Összekapcsolja a nyilatkozati személyt az intranet felhasználóval, ha ez még hiányzik.

### Munkavállaló

- Az első belépési nyilatkozatcsomagot e-mailben kapott linken tölti ki.
- Ha már van intranet felhasználója és kapcsolt nyilatkozati személy rekordja, a **Nyilatkozataim** menüpontból saját maga is indíthat éves vagy adatváltozási nyilatkozatot.
- Amíg van nyitott csomagja, nem indíthat új csomagot.
- A végleges beküldés előtt az ellenőrző oldalon egyben át kell néznie az adatokat.

## Első beléptetési folyamat

1. Munkaügy vagy toborzó létrehozza a személyt.
2. Létrejön a beléptetéshez tartozó jogviszony.
3. A toborzó elindítja az alap beléptetési csomagot.
4. Az alap csomag kötelezően tartalmazza a személyes adatok, bankszámlaszám és gyermek után járó pótszabadság nyilatkozatot.
5. Adóügyi nyilatkozat csak akkor kerül a csomagba, ha azt külön kiválasztják.
6. A rendszer e-mailt küld a kitöltési linkkel és az ANTRA azonosítóval.
7. A kitöltő megadja az ANTRA azonosítót, kitölti a nyilatkozatokat, majd az ellenőrző oldalon véglegesen beküldi a csomagot.
8. A jogosult ellenőr elfogadja vagy javításra visszaküldi a nyilatkozatokat.
9. Elfogadás után a csomag lezárható.

## Éves adóügyi nyilatkozatok

- Új adóév elején a munkavállaló saját maga indíthatja az adóügyi nyilatkozatokat a **Nyilatkozataim** menüpontból.
- Munkaügy is indíthat csomagot, ha szervezetten szeretné kiküldeni az éves nyilatkoztatást.
- A korábbi lezárt nyilatkozat nem tiltja az új beküldést, mert év közben változhat az igénylés.
- Nyitott csomag mellett nem indítható új csomag. Előbb a meglévő csomagot kell beküldeni, ellenőrizni, lezárni vagy törölni.

## Adatváltozás és bankszámlaszám módosítás

- A dolgozó saját indítású csomagban adatváltozást vagy bankszámlaszám módosítást is leadhat.
- A csomagba csak azok a nyilatkozatok kerülnek bele, amelyeket a dolgozó vagy munkaügy kiválasztott.
- Egy sima bankszámlaszám módosítás nem ad hozzá automatikusan személyes adatlapot.
- Kötelező vagy munkaügy által betett nyilatkozatot a kitöltő nem tud kivenni a csomagból.
- A **Nem kérem** gomb csak a kitöltő által utólag választott, választható nyilatkozatoknál jelenhet meg.

## Ellenőrzés és javítás

- A kitöltő először menti az egyes nyilatkozatokat.
- Ha minden szükséges nyilatkozat mentve van, megjelenik az ellenőrzés és végleges beküldés lehetősége.
- Végleges beküldés után a csomag ellenőrzésre vár.
- Ellenőrzéskor a jogosult munkatárs látja a beküldött adatokat és a beküldési bizonyítékot.
- Ha egy nyilatkozat hibás, csak az érintett nyilatkozatot kell javításra visszaküldeni.
- Javítás után a kitöltő újra menti az érintett nyilatkozatot, majd a csomagot újra beküldi.

## Státuszok közérthetően

- **Előkészítés alatt**: a csomag létrejött, de még nincs kiküldve.
- **Kiküldve**: a kitöltési linket elküldték.
- **Kitöltés alatt**: a kitöltő már megnyitotta vagy szerkeszti a csomagot.
- **Ellenőrzésre vár**: a kitöltő véglegesen beküldte a csomagot.
- **Elfogadva**: minden ellenőrzendő nyilatkozat elfogadásra került.
- **Lezárva**: adminisztratívan lezárt csomag; új csomag indítható.
- **Törölve**: a csomag nem használható tovább.

## Már nem használt nyomtatványok

Az alábbi nyomtatványok nem részei az aktív online folyamatnak:

- Nyilatkozat kieső időről
- TB kiskönyv nyilatkozat
- Jogviszony nyilatkozat
- Nyilatkozat letiltásról

## Fontos validációs szabályok

- Az adóazonosító jel, a TAJ szám és a bankszámlaszám formailag ellenőrzésre kerül.
- Bankszámlaszámnál a rendszer a számlaszám eleje alapján megpróbálja felismerni a bankot.
- Ha a bank nem ismerhető fel automatikusan, a bank nevét kézzel kell megadni.
- Családi kedvezménynél az EM kód kötelező.
- Családi kedvezménynél a JJ jogcím akkor kötelező, ha az EM kód nem `0` és nem `2`.
- Ha az EM kód `0` vagy `2`, a JJ jogcímet nem kell kitölteni.

## Gyakori hibák és teendők

- **A dolgozó nem tud saját nyilatkozatot indítani**: ellenőrizni kell, hogy van-e kapcsolt intranet felhasználója.
- **Van intranet felhasználó, de nincs nyilatkozati személy rekord**: munkaügynek össze kell kapcsolnia vagy létre kell hoznia a nyilatkozati személyt.
- **A dolgozó új csomagot indítana, de nem engedi a rendszer**: nyitott csomag van. A meglévőt kell befejezni vagy lezárni.
- **A kitöltő nem jut be a linken**: az e-mailben szereplő ANTRA azonosítót kell pontosan megadni.
- **A bankszámlás vagy más kötelező nyilatkozat mellett megjelenik a Nem kérem gomb**: ellenőrizni kell az adott csomagelem `selection_source` értékét. Kötelező vagy munkaügy által választott elemnél ez nem lehet kitöltő által választott elem.
- **A beküldött adatok nem látszanak az ellenőrzésnél**: ellenőrizni kell, hogy az adott nyilatkozathoz van-e presenter, vagyis megjelenítési szabály.

## Oktatási javaslat

Az oktatást érdemes három részre bontani:

1. **Toborzói folyamat**: személy, jogviszony, alap beléptetési csomag, kiküldés.
2. **Munkaügyi ellenőrzés**: beérkezett csomagok, adatok ellenőrzése, elfogadás, javításra küldés, lezárás.
3. **Munkavállalói használat**: ANTRA azonosítás, nyilatkozatok kitöltése, ellenőrzés, végleges beküldés, saját indítás a Nyilatkozataim menüpontból.
