# Nyilatkozat sablonverziok

A rendszer mar nem DOCX fajlokbol dolgozik. Az online kitolteshez minden tamogatott nyilatkozatnak kulon PHP urlapkezeloje van, a PDF pedig a mentett online urlapadatokbol keszul.

A verziokovetes tovabbra is adatbazisban tortenik:

1. Uj jogszabalyi vagy tartalmi valtozasnal hozz letre uj `declaration_templates` sort ugyanazzal a `code` ertekkel, uj `version` ertekkel.
2. Az `effective_from` es `effective_to` mezokkel szabalyozd, melyik verzio mikortol valaszthato.
3. A regi sort csak akkor allitsd `is_active = 0` ertekre, ha mar nem indithato uj csomaghoz.
4. A mar letrehozott csomagelemek a sablon kodjat, nevet es verziojat snapshotoljak, ezert a regi bekuldesek verzioja visszakeresheto marad.

DOCX fajlokat ebbe a mappaba mar nem kell feltolteni.
