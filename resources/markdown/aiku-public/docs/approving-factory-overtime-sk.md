---
title: Schvaľovanie nadčasov vo výrobe
summary: Ako výrobný manažér plánuje a schvaľuje nadčasy v Daily hours, pre jedného remeselníka alebo pre viacerých naraz, ako vyberie výplatu alebo náhradné voľno a ako sa víkendové nadčasy, TOIL a neschválené nadčasy zobrazujú v Payroll.
date: 2026-11-10
source_date: 2026-11-10
tags: production, payroll, overtime, artisan
category: production
---

<aside class="tldr">
V <b>Factory → Artisans → Daily hours</b> stlačte <b>Plan overtime</b> (naplánovať nadčas), zadajte plánované časy na deň a schváľte ich pre jedného alebo viacerých remeselníkov, alebo označte dni, ktoré prekročili zmenu, a stlačte <b>Overtime for … selected</b>. Vyberte <b>Paid</b> (platený) alebo <b>Time off in lieu</b> (náhradné voľno). Nadčas cez sobotu a nedeľu schválený ako platený sa vypláca sadzbou 1.5 × Band 0; nadčas cez pracovný týždeň sa platí ako bežné hodiny. Náhradné voľno sa nevypláca: pripíše sa do zostatku TOIL remeselníka. Nadčas, ktorý nikto neschválil ani nezamietol, je označený v Payroll a manažéri dostanú v pondelok ráno pripomienku, keďže mzdový týždeň sa uzamyká na konci pondelka. Výrobu možno nastaviť aj tak, že sa platí len čas zaznamenaný v rámci zmeny, takže skorší príchod alebo neskorší odchod sa zaplatí len vtedy, keď sa schváli ako nadčas.
</aside>

## Čo sa považuje za nadčas

Nadčas je čas, ktorý remeselník odpípol nad rámec svojej zmeny na daný deň (zmena z jeho pracovného rozvrhu bez neplatenej prestávky). V sobotu alebo nedeľu bez zmeny je nadčasom každá odpípnutá hodina. Štátne sviatky sa za víkend nepovažujú.

## Plánovanie nadčasov vopred

1. Otvorte **Factory → Artisans → Daily hours** a stlačte **Plan overtime**.
2. Vyberte **Day** (deň) a **Artisans** (remeselníkov; môže ich byť aj viac naraz).
3. Zadajte **Planned from** a **Planned to** (plánovaný začiatok a koniec), napríklad 07:00 a 12:00.
4. Zvoľte **Approve overtime**, potom **Paid** alebo **Time off in lieu**.
5. Stlačte **Save**.

Každý remeselník dostane vlastný záznam o tom, kto nadčas schválil, kedy a v akých plánovaných časoch. Keď remeselník odpípne, **Daily hours** ukáže plánované časy vedľa odpípnutých. Ak niekto príde skôr alebo odíde neskôr než v plánovaných časoch a v rámci zmeny o viac, než je tolerancia, deň ukáže **Outside planned times** a je znova označený, kým ho nepreveríte a nadčas neuložíte znova. Ako nadčas sa platia alebo do TOIL pripočítajú len plánované hodiny: čas odpípnutý nad ne sa platí ako bežné hodiny, kým nadčas po dni znova neuložíte.

## Schválenie nadčasov po dni

Dni, ktoré prekročili zmenu o viac než tolerancia, ukazujú **Over shift time**. Buď stlačte **Approve or not** pri dni, alebo označte viac dní a stlačte **Overtime for … selected**, aby ste ich vybavili naraz. Plánované časy tu nie sú povinné.

## Neschválenie nadčasu

Zvoľte **Do not authorise** a uveďte dôvod. Hodiny sa zo mzdy neodoberú: ostanú v **Payroll** s označením **Overtime not authorised** a vaším dôvodom, aby ich vyriešil účtovník a HR.

## Platený nadčas alebo náhradné voľno

- **Paid, sobota alebo nedeľa**: každá hodina nadčasu sa platí 1.5 × sadzbou Band 0 (1.5 × £12.71 = £19.07 za hodinu), bez ohľadu na úkolovú prácu. Na tieto hodiny sa nevzťahuje pásmová odmena ani bonus Guru. Keď sa sadzba Band 0 zmení, víkendová sadzba ju nasleduje od rovnakého dátumu.
- **Paid, pracovný deň**: platí sa ako bežné odpípnuté hodiny, s pásmami z úkolovej práce ako zvyčajne.
- **Time off in lieu (TOIL)**: hodiny nadčasu sa nevyplácajú. Pripíšu sa do zostatku TOIL remeselníka, vynásobené pomerom TOIL.

## Zostatok TOIL

Stlačte **Time off in lieu** v Daily hours, aby ste videli, koľko komu patrí, a mohli **Record TOIL taken** (zapísať vybraté TOIL: remeselník, deň a hodiny). Nemôžete zapísať viac, než je zostatok. Výrobný manažér alebo účtovník nastaví **TOIL hours per overtime hour** (hodiny TOIL za hodinu nadčasu) v tom istom okne (predvolene 1). Nadčas, ktorý už bol daný ako TOIL, si ponechá pomer, s ktorým bol schválený.

## Čo vidí účtovník v Payroll

- **Weekend overtime**: hodiny a suma pri 1.5 × Band 0, zahrnuté v celkovej sume. CSV uvádza aj sadzbu a rozdelenie týchto hodín do pásiem, len pre informáciu.
- **TOIL hours (not paid)** a **TOIL balance**: CSV pridáva dlžné hodiny, TOIL vybraté v období a zostatok na jeho konci.
- **Overtime not authorised** a **Overtime awaiting decision** vedľa mena remeselníka a ako hodiny v CSV.

Stránka Payroll ani CSV nerozhodujú o tom, ako sa TOIL počíta do minimálnej mzdy: to je vec účtovníka.

## Výroby, ktoré platia len čas v rámci zmeny

Výrobný manažér alebo účtovník môže výrobu nastaviť tak, aby sa platil len čas zaznamenaný v rámci zmeny. V **Daily hours** stlačte ozubené koliesko pri **Every clocked hour is paid**, zapnite **Pay inside the shift only**, vyberte krok zaokrúhľovania a deň, od ktorého nastavenie platí, a stlačte **Save**. Deň musí byť v týždni, ktorý ešte nie je uzamknutý pre mzdy: už vyplatené týždne zostávajú tak, ako boli.

Od toho dňa:

- Čas zaznamenaný pred začiatkom zmeny alebo po jej konci sa neplatí. **Daily hours** ho ukazuje pod zaznamenaným časom ako **not paid** a **Payroll** ukazuje pri mene remeselníka **Outside the shift, not paid**.
- Ak ho chcete zaplatiť, schváľte nadčas za daný deň tak, ako je opísané vyššie. Schválením dňa sa zaplatí čas zaznamenaný mimo zmeny, najviac do plánovaných časov, ak bol nadčas naplánovaný vopred. V sobotu alebo nedeľu bez zmeny sa neplatí nič, kým sa nadčas neschváli.
- **Do not authorise** znamená, že čas mimo zmeny zostáva nezaplatený.
- Kto príde neskoro, je platený od času príchodu zaokrúhleného na najbližší krok počítaný od začiatku zmeny (5 minút, ak ho nezmeníte): príchod o 08:02 sa platí od 08:00, príchod o 08:37 od 08:35.
- Práca zaznamenaná na tablete pred zmenou alebo po nej sa tiež neplatí.
- Guru bonus sa platí za zaplatené hodiny.

Mzda sa riadi pracovným časom každého remeselníka, preto skontrolujte, či je u zamestnanca nastavený správne. Neskoršia zmena niekoho pracovného času nezmení týždeň, ktorý je už uzamknutý pre mzdy. CSV z Payroll uvádza zaplatené hodiny a na konci každého riadku zaznamenané hodiny a hodiny zaznamenané mimo zmeny, ktoré sa neplatia.

## Pondelková pripomienka

V pondelok ráno dostanú manažéri výroby e-mail so zoznamom všetkých dní minulého týždňa, v ktorých nadčas ešte nikto neschválil ani nezamietol, s odkazom na tieto dni v Daily hours. Mzdový týždeň sa uzamyká na konci pondelka; potom ho môže zmeniť už len účtovník, a to s uvedením dôvodu.
