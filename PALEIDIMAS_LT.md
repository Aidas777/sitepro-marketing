# Paleidimo instrukcija

## Reikalavimai

- Windows kompiuteris su PHP CLI (7.4 arba naujesne versija).
- PHP plėtiniai `pdo_sqlite` ir `mbstring`.
- PHP komanda `php` turi būti pasiekiama iš komandų eilutės.

Šiame projekte naudojama SQLite duomenų bazė, todėl MySQL ar atskirai diegti duomenų bazės nereikia. Yii2 priklausomybės jau yra `vendor` aplanke.

## Paleidimas

1. Atidarykite programos aplanką: `sitepro-marketing`.
2. Dukart spustelėkite `start-local.bat`. Arba PowerShell lange tame aplanke vykdykite:

   ```powershell
   .\start-local.bat
   ```

3. Naršyklėje atidarykite [http://127.0.0.1:8010](http://127.0.0.1:8010).
4. Norėdami sustabdyti serverį, paleidimo lange paspauskite `Ctrl+C`.

Jei pranešama, kad `php` komanda nerasta, įtraukite PHP aplanką į `PATH` arba paleiskite PHP serverį rankiniu būdu iš programos aplanko:

```powershell
E:\xampp\php\php.exe -d upload_tmp_dir=runtime\uploads -d session.save_path=runtime\sessions -d sys_temp_dir=runtime\tmp -d upload_max_filesize=5M -d post_max_size=6M -S 127.0.0.1:8010 -t web
```

## Naudojimas

1. Pirmą kartą atidarius puslapį, sistema įkelia keturis pavyzdinius duomenų failus iš `data/samples`. JSON formatui išbandyti yra ir atskiras pavyzdys.
2. Skiltyje **Import data** pasirinkite šaltinį, numatytąją kalbą (naudojama, kai faile kalba nenurodyta) ir CSV arba JSON failą.
3. Skiltyje **Clean and qualify** nustatykite mažiausią paieškų skaičių ir redaguokite prekės ženklų, draudžiamų bei jau naudotų raktažodžių sąrašus. Paspauskite **Save filters and refresh**.
4. **Google Ads preview** peržiūrėkite sugrupuotus raktažodžius, reklamos tekstus ir galutinius URL. Eksportui spauskite **Export Google Ads CSV** arba **Download import file**.
5. Visus importuotus įrašus ir jų atmetimo priežastis rasite lentelėje **All imported keywords**.

CSV faile turi būti `keyword` arba `query` stulpelis. Galimi papildomi stulpeliai: paieškų apimtis (`volume`), paspaudimo kaina (`cpc`), kalba arba šalis (`language` / `country`) ir galutinis URL (`target_url` / `final_url`). JSON faile pateikite raktažodžių objektų masyvą arba vieną raktažodžio objektą.

Duomenys saugomi `runtime/sitepro.sqlite` faile. CSV eksportas pateikiamas kaip atsisiuntimas ir programos kataloge neišsaugomas. Ši demonstracinė versija neturi prisijungimo apsaugos; ją naudokite vietiniam testavimui, ne viešam diegimui.
