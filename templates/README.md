# Nyilatkozat DOCX sablonok

A rendszer a `declaration_templates.template_file` mező alapján keresi a DOCX sablont ebben a mappában.

Ev kozbeni sablonvaltozasnal javasolt folyamat:

1. Az uj DOCX fajlt tedd ebbe a mappaba.
2. A `declaration_templates` tablaban hozz letre uj sort ugyanazzal a `code` ertekkel, uj `version` ertekkel es az uj `template_file` nevvel.
3. A regi sort allitsd `effective_to` datummal vagy `is_active = 0` ertekkel inaktivra, ha mar nem valaszthato.
4. Ne torold a regi DOCX fajlt, ha mar keszult vele nyilatkozatcsomag. A csomagelemek snapshotoljak a sablon metaadatait, es a dokumentumgeneralas eloszor a snapshotolt fajlt keresi. Igy egy regi bekuldes a regi sablon alapjan generalodik ujra.

Helyorzok:

- A generátor a `${kulcs}` formatumu helyorzoket csereli.
- Az alap szemelyes, ceges es csomagadatok automatikus kulcsai peldaul: `${név}`, `${adoazonosito}`, `${taj}`, `${cég}`, `${adóév}`, `${dátum}`.
- Az adougyi nyilatkozatok kulon online urlapot kapnak. A nev, adoazonosito, TAJ, ceg es adoev nem kerul ujra bekerezesre, ezeket a rendszer a szemely/csomag adataibol tolti.
- Gyermekes nyilatkozatoknal dinamikus sorok vannak. Ha peldaul 4 gyermek van, a kitolto 4 sort rogzit, a generator pedig `gyermek_1_nev`, `gyermek_2_nev`, `gyermek_3_nev`, `gyermek_4_nev` jellegu placeholder kulcsokat is kap.
- Ha a sablon olyan helyorzot tartalmaz, amit a rendszer nem ismer automatikusan es nincs hozza kulon online urlap, a publikus kitoltofelulet mezokent bekeri.
