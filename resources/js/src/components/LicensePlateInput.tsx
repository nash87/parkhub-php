import { useState, useRef, useCallback, useEffect, useMemo } from 'react';
import { useTranslation } from 'react-i18next';
import { Warning, MapPin } from '@phosphor-icons/react';

// Complete German Unterscheidungszeichen (~400+)
const CITY_CODES: [string, string][] = [
  ['A', 'Augsburg'], ['AA', 'Aalen'], ['AB', 'Aschaffenburg'], ['AC', 'Aachen'],
  ['AH', 'Ahaus'], ['AI', 'Aidlingen'], ['AIC', 'Aichach'], ['AK', 'Altenkirchen'],
  ['AL', 'Altena'], ['ALF', 'Alfeld'], ['ALZ', 'Alzenau'], ['AM', 'Amberg'],
  ['AN', 'Ansbach'], ['ANA', 'Annaberg'], ['ANG', 'Angermünde'], ['AP', 'Apolda'],
  ['APD', 'Apolda'], ['ARN', 'Arnstadt'], ['ART', 'Artern'], ['AS', 'Amberg-Sulzbach'],
  ['ASL', 'Aschersleben'], ['ASZ', 'Aue-Schwarzenberg'], ['AUR', 'Aurich'],
  ['AW', 'Ahrweiler'], ['AZ', 'Alzey'],
  ['B', 'Berlin'], ['BA', 'Bamberg'], ['BAD', 'Baden-Baden'], ['BAR', 'Barnim'],
  ['BB', 'Böblingen'], ['BC', 'Biberach'], ['BE', 'Beckum'], ['BER', 'Bernburg'],
  ['BI', 'Bielefeld'], ['BIR', 'Birkenfeld'], ['BIT', 'Bitburg-Prüm'],
  ['BK', 'Backnang'], ['BL', 'Balingen'], ['BLB', 'Berleburg'],
  ['BLK', 'Burgenlandkreis'], ['BM', 'Bergheim'], ['BN', 'Bonn'], ['BO', 'Bochum'],
  ['BOR', 'Borken'], ['BOT', 'Bottrop'], ['BRA', 'Brake'], ['BRB', 'Brandenburg'],
  ['BS', 'Braunschweig'], ['BT', 'Bayreuth'], ['BTF', 'Bitterfeld'], ['BÖ', 'Börde'],
  ['BZ', 'Bautzen'],
  ['C', 'Chemnitz'], ['CB', 'Cottbus'], ['CE', 'Celle'], ['CHA', 'Cham'],
  ['CLP', 'Cloppenburg'], ['CO', 'Coburg'], ['COC', 'Cochem-Zell'],
  ['COE', 'Coesfeld'], ['CUX', 'Cuxhaven'], ['CW', 'Calw'],
  ['D', 'Düsseldorf'], ['DA', 'Darmstadt'], ['DAH', 'Dachau'], ['DAN', 'Dannenberg'],
  ['DAU', 'Daun'], ['DB', 'Duisburg'], ['DD', 'Dresden'], ['DE', 'Dessau'],
  ['DEG', 'Deggendorf'], ['DEL', 'Delmenhorst'], ['DGF', 'Dingolfing'],
  ['DH', 'Diepholz'], ['DL', 'Döbeln'], ['DLG', 'Dillingen'], ['DM', 'Demmin'],
  ['DN', 'Düren'], ['DO', 'Dortmund'], ['DON', 'Donauwörth'], ['DU', 'Duisburg'],
  ['DW', 'Dippoldiswalde'], ['DZ', 'Delitzsch'],
  ['E', 'Essen'], ['EA', 'Eisenach'], ['EB', 'Ebersberg'], ['EBE', 'Ebersberg'],
  ['ED', 'Erding'], ['EE', 'Elbe-Elster'], ['EF', 'Erfurt'], ['EI', 'Eichstätt'],
  ['EIC', 'Eichsfeld'], ['EIL', 'Eisleben'], ['EIN', 'Einbeck'], ['EL', 'Emsland'],
  ['EM', 'Emmendingen'], ['EMD', 'Emden'], ['EMS', 'Ems'], ['EN', 'Ennepe-Ruhr'],
  ['ER', 'Erlangen'], ['ERB', 'Erbach'], ['ERH', 'Erlangen-Höchstadt'],
  ['ERK', 'Erkelenz'], ['ERZ', 'Erzgebirgskreis'], ['ES', 'Esslingen'],
  ['ESW', 'Eschwege'], ['EU', 'Euskirchen'], ['EW', 'Eberswalde'],
  ['F', 'Frankfurt'], ['FB', 'Friedberg'], ['FD', 'Fulda'], ['FDS', 'Freudenstadt'],
  ['FF', 'Frankfurt/Oder'], ['FFB', 'Fürstenfeldbruck'], ['FG', 'Freiberg'],
  ['FI', 'Finsterwalde'], ['FL', 'Flensburg'], ['FN', 'Friedrichshafen'],
  ['FO', 'Forchheim'], ['FR', 'Freiburg'], ['FRG', 'Freyung-Grafenau'],
  ['FRI', 'Friesland'], ['FRW', 'Freienwalde'], ['FS', 'Freising'],
  ['FT', 'Frankenthal'], ['FTL', 'Freital'], ['FÜ', 'Fürth'],
  ['G', 'Gera'], ['GA', 'Gardelegen'], ['GAP', 'Garmisch-Partenkirchen'],
  ['GC', 'Glauchau'], ['GD', 'Schwäbisch Gmünd'], ['GE', 'Gelsenkirchen'],
  ['GER', 'Germersheim'], ['GF', 'Gifhorn'], ['GG', 'Groß-Gerau'],
  ['GI', 'Gießen'], ['GL', 'Gladbach'], ['GM', 'Gummersbach'], ['GN', 'Gelnhausen'],
  ['GP', 'Göppingen'], ['GR', 'Görlitz'], ['GRZ', 'Greiz'], ['GS', 'Goslar'],
  ['GT', 'Gütersloh'], ['GTH', 'Gotha'], ['GÖ', 'Göttingen'], ['GZ', 'Günzburg'],
  ['H', 'Hannover'], ['HA', 'Hagen'], ['HAB', 'Hammelburg'], ['HAL', 'Halle'],
  ['HAM', 'Hamm'], ['HB', 'Bremen'], ['HBN', 'Hildburghausen'], ['HD', 'Heidelberg'],
  ['HDH', 'Heidenheim'], ['HDL', 'Haldensleben'], ['HE', 'Helmstedt'],
  ['HEF', 'Hersfeld'], ['HEI', 'Heide'], ['HER', 'Herne'], ['HF', 'Herford'],
  ['HG', 'Homberg'], ['HGW', 'Greifswald'], ['HH', 'Hamburg'],
  ['HI', 'Hildesheim'], ['HK', 'Heidekreis'], ['HL', 'Lübeck'], ['HM', 'Hameln'],
  ['HN', 'Heilbronn'], ['HO', 'Hof'], ['HOL', 'Holzminden'], ['HOM', 'Homburg'],
  ['HP', 'Heppenheim'], ['HR', 'Homberg'], ['HS', 'Heinsberg'],
  ['HSK', 'Hochsauerlandkreis'], ['HST', 'Stralsund'], ['HU', 'Hanau'],
  ['HV', 'Havelberg'], ['HVL', 'Havelland'], ['HWI', 'Wismar'], ['HX', 'Höxter'],
  ['HZ', 'Harz'],
  ['IGB', 'St. Ingbert'], ['IK', 'Ilm-Kreis'], ['IL', 'Ilmenau'],
  ['IN', 'Ingolstadt'], ['IZ', 'Steinburg'],
  ['J', 'Jena'], ['JE', 'Jessen'], ['JL', 'Jerichower Land'],
  ['K', 'Köln'], ['KA', 'Karlsruhe'], ['KB', 'Korbach'], ['KC', 'Kronach'],
  ['KE', 'Kempten'], ['KEH', 'Kelheim'], ['KF', 'Kaufbeuren'], ['KG', 'Kissingen'],
  ['KH', 'Bad Kreuznach'], ['KI', 'Kiel'], ['KL', 'Kaiserslautern'],
  ['KLE', 'Kleve'], ['KN', 'Konstanz'], ['KO', 'Koblenz'], ['KR', 'Krefeld'],
  ['KS', 'Kassel'], ['KT', 'Kitzingen'], ['KU', 'Kulmbach'], ['KUS', 'Kusel'],
  ['KYF', 'Kyffhäuserkreis'], ['KÖ', 'Köthen'], ['KÖT', 'Köthen'],
  ['L', 'Leipzig'], ['LA', 'Landshut'], ['LAU', 'Lauf'], ['LB', 'Ludwigsburg'],
  ['LD', 'Landau'], ['LDK', 'Lahn-Dill'], ['LDS', 'Dahme-Spreewald'],
  ['LE', 'Lemgo'], ['LEO', 'Leonberg'], ['LER', 'Leer'], ['LEV', 'Leverkusen'],
  ['LG', 'Lüneburg'], ['LI', 'Lindau'], ['LIF', 'Lichtenfels'], ['LIP', 'Lippe'],
  ['LL', 'Landsberg/Lech'], ['LM', 'Limburg'], ['LÖ', 'Lörrach'],
  ['LOS', 'Oder-Spree'], ['LP', 'Lippstadt'], ['LR', 'Lahr'],
  ['LU', 'Ludwigshafen'], ['LWL', 'Ludwigslust'],
  ['M', 'München'], ['MA', 'Mannheim'], ['MB', 'Miesbach'], ['MC', 'Malchin'],
  ['MD', 'Magdeburg'], ['ME', 'Mettmann'], ['MEI', 'Meißen'],
  ['MG', 'Mönchengladbach'], ['MH', 'Mülheim'], ['MI', 'Minden'],
  ['MIL', 'Miltenberg'], ['MK', 'Märkischer Kreis'], ['ML', 'Mansfelder Land'],
  ['MM', 'Memmingen'], ['MN', 'Unterallgäu'], ['MO', 'Moers'],
  ['MOD', 'Marktoberdorf'], ['MOL', 'Märkisch-Oderland'], ['MON', 'Monschau'],
  ['MOS', 'Neckar-Odenwald'], ['MQ', 'Merseburg-Querfurt'], ['MR', 'Marburg'],
  ['MS', 'Münster'], ['MSE', 'Mecklenb. Seenplatte'], ['MSH', 'Mansfeld-Südharz'],
  ['MSP', 'Main-Spessart'], ['MST', 'Mecklenburg-Strelitz'], ['MTK', 'Main-Taunus'],
  ['MTL', 'Muldentalkreis'], ['MYK', 'Mayen-Koblenz'], ['MZ', 'Mainz'],
  ['MÜ', 'Mühldorf'],
  ['N', 'Nürnberg'], ['NB', 'Neubrandenburg'], ['ND', 'Neuburg-Schrobenhausen'],
  ['NDH', 'Nordhausen'], ['NE', 'Neuss'], ['NEA', 'Neustadt/Aisch'],
  ['NEB', 'Nebra'], ['NES', 'Bad Neustadt'], ['NEW', 'Neustadt/Waldnaab'],
  ['NF', 'Nordfriesland'], ['NI', 'Nienburg'], ['NK', 'Neunkirchen'],
  ['NL', 'Nordhausen'], ['NM', 'Neumarkt'], ['NMS', 'Neumünster'],
  ['NOH', 'Nordhorn'], ['NOL', 'Oberlausitz'], ['NOM', 'Northeim'],
  ['NR', 'Neuwied'], ['NRW', 'Nordrhein-Westfalen'], ['NU', 'Neu-Ulm'],
  ['NVP', 'Nordvorpommern'], ['NW', 'Neustadt/Weinstraße'],
  ['NWM', 'Nordwestmecklenburg'],
  ['OA', 'Oberallgäu'], ['OAL', 'Ostallgäu'], ['OB', 'Oberhausen'],
  ['OD', 'Stormarn'], ['OE', 'Olpe'], ['OF', 'Offenbach'], ['OG', 'Offenburg'],
  ['OH', 'Ostholstein'], ['OHA', 'Osterode'], ['OHV', 'Oberhavel'],
  ['OHZ', 'Osterholz'], ['OK', 'Ohrekreis'], ['OL', 'Oldenburg'],
  ['OPR', 'Ostprignitz-Ruppin'], ['OR', 'Oranienburg'], ['OS', 'Osnabrück'],
  ['OSL', 'Oberspreewald-Lausitz'], ['OVP', 'Ostvorpommern'],
  ['P', 'Potsdam'], ['PA', 'Passau'], ['PAF', 'Pfaffenhofen'],
  ['PAN', 'Rottal-Inn'], ['PB', 'Paderborn'], ['PE', 'Peine'],
  ['PF', 'Pforzheim'], ['PI', 'Pinneberg'], ['PIR', 'Pirna'], ['PLÖ', 'Plön'],
  ['PM', 'Potsdam-Mittelmark'], ['PR', 'Prignitz'], ['PS', 'Pirmasens'],
  ['R', 'Regensburg'], ['RA', 'Rastatt'], ['RD', 'Rendsburg'],
  ['RE', 'Recklinghausen'], ['REG', 'Regen'], ['RG', 'Riesa-Großenhain'],
  ['RH', 'Roth'], ['RI', 'Rinteln'], ['RO', 'Rosenheim'],
  ['ROW', 'Rotenburg/Wümme'], ['RP', 'Rhein-Pfalz'], ['RS', 'Remscheid'],
  ['RT', 'Reutlingen'], ['RV', 'Ravensburg'], ['RW', 'Rottweil'],
  ['RZ', 'Herzogtum Lauenburg'],
  ['S', 'Stuttgart'], ['SAD', 'Schwandorf'], ['SAW', 'Salzwedel'],
  ['SB', 'Saarbrücken'], ['SC', 'Schwabach'], ['SDL', 'Stendal'],
  ['SE', 'Segeberg'], ['SG', 'Solingen'], ['SH', 'Schleswig'],
  ['SHA', 'Schwäbisch Hall'], ['SHG', 'Schaumburg'], ['SHK', 'Saale-Holzland'],
  ['SI', 'Siegen'], ['SIG', 'Sigmaringen'], ['SIM', 'Simmern'],
  ['SK', 'Saalekreis'], ['SL', 'Schleswig-Flensburg'], ['SLF', 'Saalfeld'],
  ['SM', 'Schmalkalden-Meiningen'], ['SN', 'Schwerin'], ['SO', 'Soest'],
  ['SOK', 'Saale-Orla'], ['SOM', 'Sömmerda'], ['SON', 'Sonneberg'],
  ['SP', 'Speyer'], ['SPN', 'Spree-Neiße'], ['SR', 'Straubing'],
  ['SRB', 'Straubing-Bogen'], ['ST', 'Steinfurt'], ['STA', 'Starnberg'],
  ['STD', 'Stade'], ['SU', 'Rhein-Sieg'], ['SUL', 'Sulzbach'],
  ['SÖM', 'Sömmerda'], ['SW', 'Schweinfurt'], ['SZ', 'Salzgitter'],
  ['TBB', 'Tauberbischofsheim'], ['TDO', 'Torgau'], ['TE', 'Tecklenburg'],
  ['TF', 'Teltow-Fläming'], ['TG', 'Torgau'], ['TIR', 'Tirschenreuth'],
  ['TO', 'Torgau'], ['TR', 'Trier'], ['TS', 'Traunstein'], ['TUT', 'Tuttlingen'],
  ['TÜ', 'Tübingen'],
  ['UE', 'Uelzen'], ['UH', 'Unstrut-Hainich'], ['UL', 'Ulm'],
  ['UM', 'Uckermark'], ['UN', 'Unna'],
  ['V', 'Vogtlandkreis'], ['VB', 'Vogelsbergkreis'], ['VEC', 'Vechta'],
  ['VER', 'Verden'], ['VIE', 'Viersen'], ['VK', 'Völklingen'],
  ['VR', 'Vorpommern-Rügen'], ['VS', 'Villingen-Schwenningen'],
  ['W', 'Wuppertal'], ['WA', 'Waldeck'], ['WAF', 'Warendorf'],
  ['WAK', 'Wartburgkreis'], ['WB', 'Wittenberg'], ['WE', 'Weimar'],
  ['WEN', 'Weiden'], ['WES', 'Wesel'], ['WF', 'Wolfenbüttel'],
  ['WHV', 'Wilhelmshaven'], ['WI', 'Wiesbaden'], ['WIL', 'Wittlich'],
  ['WK', 'Wittstock'], ['WL', 'Harburg'], ['WM', 'Weilheim-Schongau'],
  ['WMS', 'Wolmirstedt'], ['WN', 'Rems-Murr'], ['WO', 'Worms'],
  ['WOB', 'Wolfsburg'], ['WR', 'Wernigerode'], ['WS', 'Wasserburg'],
  ['WSF', 'Weißenfels'], ['WT', 'Waldshut'], ['WTM', 'Wittmund'],
  ['WUG', 'Weißenburg-Gunzenhausen'], ['WUN', 'Wunsiedel'],
  ['WW', 'Westerwald'], ['WZ', 'Wetzlar'],
  ['Z', 'Zwickau'], ['ZE', 'Zerbst'], ['ZI', 'Zittau'], ['ZR', 'Zeulenroda'],
  ['ZW', 'Zweibrücken'], ['ZZ', 'Zeitz'],
];

// Build lookup map
const CITY_MAP = new Map(CITY_CODES);

const PLATE_REGEX = /^[A-ZÄÖÜ]{1,3}-[A-Z]{1,2}\s\d{1,4}$/;

interface LicensePlateInputProps {
  value: string;
  onChange: (value: string) => void;
  className?: string;
  required?: boolean;
  autoFocus?: boolean;
}

export function LicensePlateInput({ value, onChange, className = '', required, autoFocus }: LicensePlateInputProps) {
  const { t } = useTranslation();
  const [error, setError] = useState('');
  const [touched, setTouched] = useState(false);
  const [showDropdown, setShowDropdown] = useState(false);
  const [highlightIdx, setHighlightIdx] = useState(0);
  const [selectedCity, setSelectedCity] = useState<string | null>(null);
  const inputRef = useRef<HTMLInputElement>(null);
  const dropdownRef = useRef<HTMLDivElement>(null);

  // Parse the current value to detect city code portion
  const cityInput = useMemo(() => {
    if (selectedCity) return selectedCity;
    const match = value.match(/^([A-ZÄÖÜ]{1,3})/);
    return match ? match[1] : '';
  }, [value, selectedCity]);

  // Filter city codes based on current input
  const filteredCities = useMemo(() => {
    if (!cityInput || selectedCity) return [];
    const upper = cityInput.toUpperCase();
    return CITY_CODES.filter(([code]) => code.startsWith(upper)).slice(0, 10);
  }, [cityInput, selectedCity]);

  // Detect selected city from existing value (e.g. when editing)
  const inferredCity = useMemo(() => {
    if (!value || selectedCity) return null;
    const dashIdx = value.indexOf('-');
    if (dashIdx <= 0) return null;
    const code = value.substring(0, dashIdx);
    return CITY_MAP.has(code) ? code : null;
  }, [value, selectedCity]);

  useEffect(() => {
    if (inferredCity) {
      setTimeout(() => setSelectedCity(inferredCity), 0);
    }
  }, [inferredCity]);

  // Show dropdown when typing city portion and not yet selected
  const shouldShowDropdown = useMemo(() => filteredCities.length > 0 && !selectedCity, [filteredCities, selectedCity]);

  useEffect(() => {
    setTimeout(() => {
      setShowDropdown(shouldShowDropdown);
      setHighlightIdx(0);
    }, 0);
  }, [shouldShowDropdown]);

  // Close dropdown on outside click
  useEffect(() => {
    const handler = (e: MouseEvent) => {
      if (dropdownRef.current && !dropdownRef.current.contains(e.target as Node) &&
          inputRef.current && !inputRef.current.contains(e.target as Node)) {
        setShowDropdown(false);
      }
    };
    document.addEventListener('mousedown', handler);
    return () => document.removeEventListener('mousedown', handler);
  }, []);

  const selectCity = useCallback((code: string) => {
    setSelectedCity(code);
    setShowDropdown(false);
    const afterDash = value.includes('-') ? value.substring(value.indexOf('-') + 1) : '';
    const newVal = code + '-' + afterDash;
    onChange(newVal);
    // Focus input and place cursor after dash
    setTimeout(() => inputRef.current?.focus(), 0);
  }, [value, onChange]);

  const handleChange = useCallback((e: React.ChangeEvent<HTMLInputElement>) => {
    const raw = e.target.value.toUpperCase();

    // If user clears to empty, reset city selection
    if (!raw) {
      setSelectedCity(null);
      onChange('');
      setError('');
      return;
    }

    // If user is backspacing past the dash, reset city selection
    if (selectedCity && !raw.includes('-')) {
      setSelectedCity(null);
      onChange(raw);
      return;
    }

    // Auto-format: city code already selected
    if (selectedCity) {
      // Ensure it starts with city code + dash
      if (!raw.startsWith(selectedCity + '-')) {
        // User might be editing the city part — reset
        if (!raw.startsWith(selectedCity)) {
          setSelectedCity(null);
          onChange(raw.replace(/[^A-ZÄÖÜ0-9]/g, ''));
          return;
        }
      }

      // Format the part after city code
      const afterCity = raw.substring(selectedCity.length).replace(/[^A-ZÄÖÜ0-9-\s]/g, '');
      let letters = '';
      let numbers = '';
      let inNumbers = false;

      for (const ch of afterCity.replace(/[-\s]/g, '')) {
        if (!inNumbers && /[A-ZÄÖÜ]/.test(ch) && letters.length < 2) {
          letters += ch;
        } else if (/\d/.test(ch) && numbers.length < 4) {
          numbers += ch;
          inNumbers = true;
        }
      }

      let formatted = selectedCity + '-' + letters;
      if (numbers) {
        formatted += ' ' + numbers;
      }
      onChange(formatted);

      if (touched && formatted && !PLATE_REGEX.test(formatted)) {
        setError(t('vehicle.formatHint'));
      } else {
        setError('');
      }
      return;
    }

    // No city selected yet — just uppercase letters for city portion
    const cleaned = raw.replace(/[^A-ZÄÖÜ]/g, '').substring(0, 3);
    onChange(cleaned);
    setError('');
  }, [selectedCity, onChange, touched, t]);

  const handleKeyDown = useCallback((e: React.KeyboardEvent) => {
    if (showDropdown && filteredCities.length > 0) {
      if (e.key === 'ArrowDown') {
        e.preventDefault();
        setHighlightIdx(i => Math.min(i + 1, filteredCities.length - 1));
      } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        setHighlightIdx(i => Math.max(i - 1, 0));
      } else if (e.key === 'Enter' || e.key === 'Tab') {
        e.preventDefault();
        selectCity(filteredCities[highlightIdx][0]);
      } else if (e.key === 'Escape') {
        setShowDropdown(false);
      }
    }
  }, [showDropdown, filteredCities, highlightIdx, selectCity]);

  const handleBlur = useCallback(() => {
    setTouched(true);
    // Delay to allow dropdown click
    setTimeout(() => setShowDropdown(false), 150);
    if (value && !PLATE_REGEX.test(value)) {
      setError(t('vehicle.formatHint'));
    } else {
      setError('');
    }
  }, [value, t]);

  const cityName = selectedCity ? CITY_MAP.get(selectedCity) : null;

  return (
    <div className="relative">
      <div className="relative">
        <input
          ref={inputRef}
          type="text"
          value={value}
          onChange={handleChange}
          onKeyDown={handleKeyDown}
          onBlur={handleBlur}
          onFocus={() => {
            if (!selectedCity && cityInput) setShowDropdown(true);
          }}
          placeholder="GÖ-AB 1234"
          className={`input font-mono text-lg tracking-wider ${error ? 'border-red-400 dark:border-red-500' : ''} ${className}`}
          required={required}
          autoFocus={autoFocus}
          autoCapitalize="characters"
          autoComplete="off"
          inputMode="text"
        />
      </div>

      {/* Dropdown */}
      {showDropdown && filteredCities.length > 0 && (
        <div
          ref={dropdownRef}
          className="absolute z-50 mt-1 w-full bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-lg overflow-hidden"
        >
          {filteredCities.map(([code, name], idx) => (
            <button
              key={code}
              type="button"
              className={`w-full text-left px-3 py-2 flex items-center gap-3 text-sm transition-colors ${
                idx === highlightIdx
                  ? 'bg-primary-50 dark:bg-primary-900/30 text-primary-700 dark:text-primary-300'
                  : 'text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700/50'
              }`}
              onMouseDown={(e) => {
                e.preventDefault();
                selectCity(code);
              }}
              onMouseEnter={() => setHighlightIdx(idx)}
            >
              <span className="font-mono font-bold text-base min-w-[3rem]">
                <span className="text-primary-600 dark:text-primary-400">{code.substring(0, cityInput.length)}</span>
                <span>{code.substring(cityInput.length)}</span>
              </span>
              <span className="text-gray-500 dark:text-gray-400 truncate">{name}</span>
            </button>
          ))}
        </div>
      )}

      {/* City name + format hint */}
      <div className="flex items-center justify-between mt-1.5">
        {cityName ? (
          <span className="text-xs text-primary-500 dark:text-primary-400 flex items-center gap-1">
            <MapPin weight="bold" className="w-3 h-3" />
            {cityName}
          </span>
        ) : (
          <span className="text-xs text-gray-400 dark:text-gray-500 font-mono">{t('vehicle.formatHint')}</span>
        )}
        {error && touched && (
          <span className="text-xs text-red-500 flex items-center gap-1">
            <Warning weight="bold" className="w-3 h-3" />
            {error}
          </span>
        )}
      </div>
    </div>
  );
}
