<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class VehicleController extends Controller
{
    public function index(Request $request)
    {
        return response()->json(Vehicle::where('user_id', $request->user()->id)->get());
    }

    public function store(Request $request)
    {
        $request->validate(['plate' => 'required|string']);
        $vehicle = Vehicle::create(array_merge(
            $request->only(['plate', 'make', 'model', 'color', 'is_default']),
            ['user_id' => $request->user()->id]
        ));
        return response()->json($vehicle, 201);
    }

    public function update(Request $request, string $id)
    {
        $vehicle = Vehicle::where('user_id', $request->user()->id)->findOrFail($id);
        $vehicle->update($request->only(['plate', 'make', 'model', 'color', 'is_default']));
        return response()->json($vehicle);
    }

    public function destroy(Request $request, string $id)
    {
        $vehicle = Vehicle::where('user_id', $request->user()->id)->findOrFail($id);

        // Remove photo if present
        if ($vehicle->photo_url) {
            Storage::disk('local')->delete("vehicles/{$vehicle->id}.jpg");
        }

        $vehicle->delete();
        return response()->json(['message' => 'Deleted']);
    }

    public function uploadPhoto(Request $request, string $id)
    {
        $vehicle = Vehicle::where('user_id', $request->user()->id)->findOrFail($id);

        $request->validate([
            'photo' => 'required_without:photo_base64',
            'photo_base64' => 'required_without:photo|string',
        ]);

        if ($request->hasFile('photo')) {
            $imageData = file_get_contents($request->file('photo')->getRealPath());
        } else {
            $base64 = $request->photo_base64;
            // Strip data URL prefix if present
            if (str_contains($base64, ',')) {
                $base64 = explode(',', $base64, 2)[1];
            }
            $imageData = base64_decode($base64);
        }

        // Resize using GD to max 800px
        $imageData = $this->resizeImage($imageData, 800);

        $dir  = storage_path('app/vehicles');
        if (!is_dir($dir)) mkdir($dir, 0755, true);

        file_put_contents("{$dir}/{$vehicle->id}.jpg", $imageData);

        $photoUrl = "/api/v1/vehicles/{$vehicle->id}/photo";
        $vehicle->update(['photo_url' => $photoUrl]);

        return response()->json(['success' => true, 'data' => ['photo_url' => $photoUrl]]);
    }

    public function servePhoto(string $id)
    {
        $path = storage_path("app/vehicles/{$id}.jpg");

        if (!file_exists($path)) {
            return response()->json(['error' => 'Photo not found'], 404);
        }

        return response()->file($path, ['Content-Type' => 'image/jpeg']);
    }

    private function resizeImage(string $data, int $maxPx): string
    {
        $src = @imagecreatefromstring($data);
        if (!$src) {
            return $data; // can't decode — return as-is
        }

        $w = imagesx($src);
        $h = imagesy($src);

        if ($w <= $maxPx && $h <= $maxPx) {
            // Already small enough — just re-encode as JPEG
            ob_start();
            imagejpeg($src, null, 85);
            $out = ob_get_clean();
            imagedestroy($src);
            return $out;
        }

        if ($w >= $h) {
            $newW = $maxPx;
            $newH = (int) round($h * ($maxPx / $w));
        } else {
            $newH = $maxPx;
            $newW = (int) round($w * ($maxPx / $h));
        }

        $dst = imagecreatetruecolor($newW, $newH);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $newW, $newH, $w, $h);

        ob_start();
        imagejpeg($dst, null, 85);
        $out = ob_get_clean();

        imagedestroy($src);
        imagedestroy($dst);

        return $out;
    }

    public function cityCodes()
    {
        // 400+ German Unterscheidungszeichen
        $codes = [
            ['code' => 'A', 'city' => 'Augsburg'], ['code' => 'AB', 'city' => 'Aschaffenburg'],
            ['code' => 'ABI', 'city' => 'Anhalt-Bitterfeld'], ['code' => 'ABG', 'city' => 'Altenburger Land'],
            ['code' => 'AC', 'city' => 'Aachen'], ['code' => 'ADF', 'city' => 'Dillingen a.d.Donau'],
            ['code' => 'AE', 'city' => 'Vogtlandkreis'], ['code' => 'AEM', 'city' => 'Ebersberg'],
            ['code' => 'AER', 'city' => 'Straubing-Bogen'], ['code' => 'AIC', 'city' => 'Aichach-Friedberg'],
            ['code' => 'AK', 'city' => 'Altenkirchen'], ['code' => 'AKÜ', 'city' => 'Kürnbach'],
            ['code' => 'ALN', 'city' => 'Alb-Donau-Kreis'], ['code' => 'ALS', 'city' => 'Alzenau'],
            ['code' => 'ALT', 'city' => 'Altötting'], ['code' => 'AM', 'city' => 'Amberg'],
            ['code' => 'AMB', 'city' => 'Amberg-Sulzbach'], ['code' => 'AN', 'city' => 'Ansbach'],
            ['code' => 'ANA', 'city' => 'Annaberg'], ['code' => 'ANK', 'city' => 'Anklam'],
            ['code' => 'AOA', 'city' => 'Ammerland'], ['code' => 'AÖ', 'city' => 'Altötting'],
            ['code' => 'APD', 'city' => 'Dachau'], ['code' => 'ARN', 'city' => 'Arnsberg'],
            ['code' => 'ARS', 'city' => 'Arzberg'], ['code' => 'AS', 'city' => 'Amberg-Sulzbach'],
            ['code' => 'ASL', 'city' => 'Aschersleben-Staßfurt'], ['code' => 'ASZ', 'city' => 'Aue-Schwarzenberg'],
            ['code' => 'AUR', 'city' => 'Aurich'], ['code' => 'AW', 'city' => 'Ahrweiler'],
            ['code' => 'AWT', 'city' => 'Waldshut'], ['code' => 'AZE', 'city' => 'Anhalt-Zerbst'],
            ['code' => 'AZW', 'city' => 'Alzey-Worms'],
            ['code' => 'B', 'city' => 'Berlin'], ['code' => 'BA', 'city' => 'Bamberg'],
            ['code' => 'BAD', 'city' => 'Baden-Baden'], ['code' => 'BAL', 'city' => 'Balinge'],
            ['code' => 'BAR', 'city' => 'Barnim'], ['code' => 'BB', 'city' => 'Böblingen'],
            ['code' => 'BBG', 'city' => 'Bernburg'], ['code' => 'BBL', 'city' => 'Brandenburg an der Havel'],
            ['code' => 'BBN', 'city' => 'Bernkastel-Wittlich'], ['code' => 'BC', 'city' => 'Biberach'],
            ['code' => 'BCH', 'city' => 'Bischoffsheim'], ['code' => 'BD', 'city' => 'Wiesbaden'],
            ['code' => 'BE', 'city' => 'Lüchow-Dannenberg'], ['code' => 'BEI', 'city' => 'Eichstätt'],
            ['code' => 'BER', 'city' => 'Berchtesgadener Land'], ['code' => 'BES', 'city' => 'Weißenburg-Gunzenhausen'],
            ['code' => 'BF', 'city' => 'Bitburg-Prüm'], ['code' => 'BGL', 'city' => 'Berchtesgadener Land'],
            ['code' => 'BGN', 'city' => 'Bogen'], ['code' => 'BI', 'city' => 'Bielefeld'],
            ['code' => 'BIR', 'city' => 'Birkenfeld'], ['code' => 'BIS', 'city' => 'Bismark'],
            ['code' => 'BIT', 'city' => 'Bitburg-Prüm'], ['code' => 'BIW', 'city' => 'Wittmund'],
            ['code' => 'BK', 'city' => 'Rems-Murr-Kreis'], ['code' => 'BKS', 'city' => 'Hildburghausen'],
            ['code' => 'BL', 'city' => 'Zollernalbkreis'], ['code' => 'BLB', 'city' => 'Siegen-Wittgenstein'],
            ['code' => 'BLK', 'city' => 'Burgenlandkreis'], ['code' => 'BM', 'city' => 'Erftkreis'],
            ['code' => 'BN', 'city' => 'Bonn'], ['code' => 'BO', 'city' => 'Bochum'],
            ['code' => 'BOG', 'city' => 'Straubing-Bogen'], ['code' => 'BOH', 'city' => 'Regen'],
            ['code' => 'BOR', 'city' => 'Borken'], ['code' => 'BOS', 'city' => 'Biberach'],
            ['code' => 'BOT', 'city' => 'Bottrop'], ['code' => 'BR', 'city' => 'Breisgau-Hochschwarzwald'],
            ['code' => 'BRA', 'city' => 'Wesermarsch'], ['code' => 'BRB', 'city' => 'Brandenburg an der Havel'],
            ['code' => 'BRG', 'city' => 'Börde'], ['code' => 'BRK', 'city' => 'Rosenheim'],
            ['code' => 'BRL', 'city' => 'Braunlage'], ['code' => 'BRM', 'city' => 'Borken'],
            ['code' => 'BRN', 'city' => 'Rheinisch-Bergischer Kreis'],
            ['code' => 'BRS', 'city' => 'Bremen'], ['code' => 'BS', 'city' => 'Braunschweig'],
            ['code' => 'BSB', 'city' => 'Breisgau-Hochschwarzwald'], ['code' => 'BSK', 'city' => 'Spree-Neiße'],
            ['code' => 'BT', 'city' => 'Bayreuth'], ['code' => 'BTF', 'city' => 'Bitterfeld'],
            ['code' => 'BÜD', 'city' => 'Wetteraukreis'], ['code' => 'BÜS', 'city' => 'Büsum'],
            ['code' => 'BÜZ', 'city' => 'Bützow'], ['code' => 'BW', 'city' => 'Bergstraße'],
            ['code' => 'BWL', 'city' => 'Waldeck-Frankenberg'], ['code' => 'BYN', 'city' => 'Rhön-Grabfeld'],
            ['code' => 'BZA', 'city' => 'Zwickau'],
            ['code' => 'C', 'city' => 'Chemnitz'], ['code' => 'CA', 'city' => 'Celle'],
            ['code' => 'CB', 'city' => 'Cottbus'], ['code' => 'CE', 'city' => 'Celle'],
            ['code' => 'CES', 'city' => 'Celle'], ['code' => 'CHE', 'city' => 'Herzogtum Lauenburg'],
            ['code' => 'CHP', 'city' => 'Peine'], ['code' => 'CLP', 'city' => 'Cloppenburg'],
            ['code' => 'CLZ', 'city' => 'Clausthal-Zellerfeld'], ['code' => 'CO', 'city' => 'Coburg'],
            ['code' => 'COC', 'city' => 'Cochem-Zell'], ['code' => 'COE', 'city' => 'Coesfeld'],
            ['code' => 'CR', 'city' => 'Kronach'], ['code' => 'CUX', 'city' => 'Cuxhaven'],
            ['code' => 'CW', 'city' => 'Calw'],
            ['code' => 'D', 'city' => 'Düsseldorf'], ['code' => 'DA', 'city' => 'Darmstadt'],
            ['code' => 'DAH', 'city' => 'Dachau'], ['code' => 'DAN', 'city' => 'Lüchow-Dannenberg'],
            ['code' => 'DAU', 'city' => 'Daun'], ['code' => 'DB', 'city' => 'Dithmarschen'],
            ['code' => 'DBR', 'city' => 'Bad Doberan'], ['code' => 'DD', 'city' => 'Dresden'],
            ['code' => 'DE', 'city' => 'Dessau'], ['code' => 'DEG', 'city' => 'Deggendorf'],
            ['code' => 'DEL', 'city' => 'Delmenhorst'], ['code' => 'DGF', 'city' => 'Dingolfing-Landau'],
            ['code' => 'DH', 'city' => 'Diepholz'], ['code' => 'DI', 'city' => 'Darmstadt'],
            ['code' => 'DIG', 'city' => 'Dingolfing'], ['code' => 'DIL', 'city' => 'Dillenburg'],
            ['code' => 'DIN', 'city' => 'Dinslaken'], ['code' => 'DKB', 'city' => 'Donau-Ries'],
            ['code' => 'DL', 'city' => 'Döbeln'], ['code' => 'DLG', 'city' => 'Dillingen a.d.Donau'],
            ['code' => 'DM', 'city' => 'Donnersbergkreis'], ['code' => 'DN', 'city' => 'Düren'],
            ['code' => 'DNK', 'city' => 'Donaukreis'], ['code' => 'DO', 'city' => 'Dortmund'],
            ['code' => 'DON', 'city' => 'Donau-Ries'], ['code' => 'DOR', 'city' => 'Dortmund'],
            ['code' => 'DU', 'city' => 'Duisburg'], ['code' => 'DÜW', 'city' => 'Bad Dürkheim'],
            ['code' => 'DÜR', 'city' => 'Düren'], ['code' => 'DW', 'city' => 'Weißeritzkreis'],
            ['code' => 'DZ', 'city' => 'Delitzsch'],
            ['code' => 'E', 'city' => 'Essen'], ['code' => 'EA', 'city' => 'Eisenach'],
            ['code' => 'EBE', 'city' => 'Ebersberg'], ['code' => 'EBN', 'city' => 'Eifelkreis Bitburg-Prüm'],
            ['code' => 'EBR', 'city' => 'Bamberg'], ['code' => 'EBT', 'city' => 'Bayreuth'],
            ['code' => 'ECK', 'city' => 'Eckernförde'], ['code' => 'EI', 'city' => 'Eichstätt'],
            ['code' => 'EIC', 'city' => 'Eichsfeld'], ['code' => 'EIL', 'city' => 'Eilenburg'],
            ['code' => 'EIN', 'city' => 'Einsiedel'], ['code' => 'EIS', 'city' => 'Eisenach'],
            ['code' => 'EL', 'city' => 'Emsland'], ['code' => 'ELT', 'city' => 'Elbe-Elster'],
            ['code' => 'EMD', 'city' => 'Emden'], ['code' => 'EMS', 'city' => 'Rhein-Lahn-Kreis'],
            ['code' => 'EN', 'city' => 'Ennepe-Ruhr-Kreis'], ['code' => 'ENZ', 'city' => 'Enzkreis'],
            ['code' => 'EPH', 'city' => 'Epphain'], ['code' => 'ER', 'city' => 'Erlangen'],
            ['code' => 'ERB', 'city' => 'Odenwaldkreis'], ['code' => 'ERG', 'city' => 'Erzgebirgskreis'],
            ['code' => 'ERH', 'city' => 'Erlangen-Höchstadt'], ['code' => 'ERN', 'city' => 'Lahn-Dill-Kreis'],
            ['code' => 'ERZ', 'city' => 'Erzgebirgskreis'], ['code' => 'ES', 'city' => 'Esslingen'],
            ['code' => 'ESB', 'city' => 'Roth'], ['code' => 'ESG', 'city' => 'Weserbergland'],
            ['code' => 'ESW', 'city' => 'Werra-Meißner-Kreis'], ['code' => 'EU', 'city' => 'Euskirchen'],
            ['code' => 'EUT', 'city' => 'Germersheim'], ['code' => 'EW', 'city' => 'Wittenberg'],
            ['code' => 'F', 'city' => 'Frankfurt am Main'], ['code' => 'FA', 'city' => 'Frankenthal'],
            ['code' => 'FB', 'city' => 'Wetteraukreis'], ['code' => 'FD', 'city' => 'Fulda'],
            ['code' => 'FDB', 'city' => 'Fürstenfeldbruck'], ['code' => 'FDS', 'city' => 'Freudenstadt'],
            ['code' => 'FED', 'city' => 'Federsee'], ['code' => 'FEU', 'city' => 'Feuchtwangen'],
            ['code' => 'FFB', 'city' => 'Fürstenfeldbruck'], ['code' => 'FKB', 'city' => 'Forchheim'],
            ['code' => 'FL', 'city' => 'Flensburg'], ['code' => 'FLÖ', 'city' => 'Flöha'],
            ['code' => 'FN', 'city' => 'Bodenseekreis'], ['code' => 'FNS', 'city' => 'Freising'],
            ['code' => 'FO', 'city' => 'Forchheim'], ['code' => 'FR', 'city' => 'Freiburg im Breisgau'],
            ['code' => 'FRE', 'city' => 'Fürstenfeldbruck'], ['code' => 'FRG', 'city' => 'Freyung-Grafenau'],
            ['code' => 'FRI', 'city' => 'Friesland'], ['code' => 'FRN', 'city' => 'Rhein-Neckar-Kreis'],
            ['code' => 'FRS', 'city' => 'Freising'], ['code' => 'FRW', 'city' => 'Frankenberg'],
            ['code' => 'FS', 'city' => 'Freising'], ['code' => 'FSD', 'city' => 'Fürstenfeldbruck'],
            ['code' => 'FST', 'city' => 'Fürstenwalde'], ['code' => 'FT', 'city' => 'Frankenthal'],
            ['code' => 'FTL', 'city' => 'Freiberg'], ['code' => 'FÜ', 'city' => 'Fürth'],
            ['code' => 'FÜS', 'city' => 'Ostallgäu'], ['code' => 'FW', 'city' => 'Fürth'],
            ['code' => 'FWL', 'city' => 'Waldshut'],
            ['code' => 'G', 'city' => 'Gera'], ['code' => 'GA', 'city' => 'Grafschaft Bentheim'],
            ['code' => 'GAP', 'city' => 'Garmisch-Partenkirchen'], ['code' => 'GAR', 'city' => 'Garmisch-Partenkirchen'],
            ['code' => 'GC', 'city' => 'Chemnitz'], ['code' => 'GD', 'city' => 'Schwäbisch Gmünd'],
            ['code' => 'GDB', 'city' => 'Bautzen'], ['code' => 'GE', 'city' => 'Gelsenkirchen'],
            ['code' => 'GEI', 'city' => 'Geilenkirchen'], ['code' => 'GEO', 'city' => 'Georgsmarienhütte'],
            ['code' => 'GER', 'city' => 'Germersheim'], ['code' => 'GF', 'city' => 'Gifhorn'],
            ['code' => 'GG', 'city' => 'Groß-Gerau'], ['code' => 'GHA', 'city' => 'Landsberg am Lech'],
            ['code' => 'GHC', 'city' => 'Garmisch-Partenkirchen'], ['code' => 'GI', 'city' => 'Gießen'],
            ['code' => 'GK', 'city' => 'Goslar'], ['code' => 'GKN', 'city' => 'Göppingen'],
            ['code' => 'GL', 'city' => 'Rheinisch-Bergischer Kreis'], ['code' => 'GLA', 'city' => 'Clausthal-Zellerfeld'],
            ['code' => 'GM', 'city' => 'Oberbergischer Kreis'], ['code' => 'GMN', 'city' => 'Schwäbisch Gmünd'],
            ['code' => 'GN', 'city' => 'Gelnhausen'], ['code' => 'GNT', 'city' => 'Günzburg'],
            ['code' => 'GO', 'city' => 'Göttingen'], ['code' => 'GOA', 'city' => 'Gotha'],
            ['code' => 'GOH', 'city' => 'Göttingen'], ['code' => 'GP', 'city' => 'Göppingen'],
            ['code' => 'GR', 'city' => 'Görlitz'], ['code' => 'GRA', 'city' => 'Grafenau'],
            ['code' => 'GRH', 'city' => 'Greifswald'], ['code' => 'GRI', 'city' => 'Griesbach'],
            ['code' => 'GRM', 'city' => 'Grimma'], ['code' => 'GRO', 'city' => 'Groß-Gerau'],
            ['code' => 'GRS', 'city' => 'Gransee'], ['code' => 'GRW', 'city' => 'Greifswald'],
            ['code' => 'GRZ', 'city' => 'Greiz'], ['code' => 'GS', 'city' => 'Goslar'],
            ['code' => 'GT', 'city' => 'Gütersloh'], ['code' => 'GTH', 'city' => 'Gotha'],
            ['code' => 'GUB', 'city' => 'Guben'], ['code' => 'GUN', 'city' => 'Günzburg'],
            ['code' => 'GVM', 'city' => 'Germersheim'], ['code' => 'GW', 'city' => 'Greifswald'],
            ['code' => 'GYN', 'city' => 'Goslar'],
            ['code' => 'H', 'city' => 'Hannover'], ['code' => 'HA', 'city' => 'Hagen'],
            ['code' => 'HAB', 'city' => 'Rhön-Grabfeld'], ['code' => 'HAL', 'city' => 'Halle (Saale)'],
            ['code' => 'HAM', 'city' => 'Hamm'], ['code' => 'HAS', 'city' => 'Haßberge'],
            ['code' => 'HB', 'city' => 'Bremen'], ['code' => 'HBN', 'city' => 'Hildburghausen'],
            ['code' => 'HBS', 'city' => 'Halberstadt'], ['code' => 'HCH', 'city' => 'Heidenheim'],
            ['code' => 'HD', 'city' => 'Heidelberg'], ['code' => 'HDH', 'city' => 'Heidenheim'],
            ['code' => 'HE', 'city' => 'Helmstedt'], ['code' => 'HEB', 'city' => 'Herzogtum Lauenburg'],
            ['code' => 'HEF', 'city' => 'Hersfeld-Rotenburg'], ['code' => 'HEI', 'city' => 'Dithmarschen'],
            ['code' => 'HEM', 'city' => 'Herne'], ['code' => 'HER', 'city' => 'Herne'],
            ['code' => 'HES', 'city' => 'Spree-Neiße'], ['code' => 'HGN', 'city' => 'Hildburghausen'],
            ['code' => 'HGW', 'city' => 'Greifswald'], ['code' => 'HH', 'city' => 'Hamburg'],
            ['code' => 'HHM', 'city' => 'Hamm'], ['code' => 'HI', 'city' => 'Hildesheim'],
            ['code' => 'HIG', 'city' => 'Eichsfeld'], ['code' => 'HIP', 'city' => 'Roth'],
            ['code' => 'HK', 'city' => 'Heidekreis'], ['code' => 'HL', 'city' => 'Hansestadt Lübeck'],
            ['code' => 'HLD', 'city' => 'Landkreis Hildesheim'], ['code' => 'HLS', 'city' => 'Rotenburg (Wümme)'],
            ['code' => 'HM', 'city' => 'Hameln-Pyrmont'], ['code' => 'HMÜ', 'city' => 'Göttingen'],
            ['code' => 'HN', 'city' => 'Heilbronn'], ['code' => 'HNO', 'city' => 'Northeim'],
            ['code' => 'HO', 'city' => 'Hof'], ['code' => 'HOB', 'city' => 'Grafschaft Bentheim'],
            ['code' => 'HOC', 'city' => 'Hochsauerlandkreis'], ['code' => 'HOG', 'city' => 'Hohenlohekreis'],
            ['code' => 'HOK', 'city' => 'Holzminden'], ['code' => 'HOM', 'city' => 'Saarpfalz-Kreis'],
            ['code' => 'HOR', 'city' => 'Horb'], ['code' => 'HOS', 'city' => 'Hochsauerlandkreis'],
            ['code' => 'HP', 'city' => 'Bergstraße'], ['code' => 'HR', 'city' => 'Hersfeld-Rotenburg'],
            ['code' => 'HRO', 'city' => 'Rostock'], ['code' => 'HS', 'city' => 'Heinsberg'],
            ['code' => 'HSK', 'city' => 'Hochsauerlandkreis'], ['code' => 'HST', 'city' => 'Stralsund'],
            ['code' => 'HU', 'city' => 'Main-Kinzig-Kreis'], ['code' => 'HV', 'city' => 'Havelland'],
            ['code' => 'HVL', 'city' => 'Havelland'], ['code' => 'HWI', 'city' => 'Wismar'],
            ['code' => 'HX', 'city' => 'Höxter'], ['code' => 'HZ', 'city' => 'Harz'],
            ['code' => 'IGB', 'city' => 'St. Ingbert'], ['code' => 'IGS', 'city' => 'Grafschaft Bentheim'],
            ['code' => 'IK', 'city' => 'Ilm-Kreis'], ['code' => 'IL', 'city' => 'Ilmenau'],
            ['code' => 'ILL', 'city' => 'Ilm-Kreis'], ['code' => 'IN', 'city' => 'Ingolstadt'],
            ['code' => 'INO', 'city' => 'Ingolstadt'], ['code' => 'IZ', 'city' => 'Steinburg'],
            ['code' => 'J', 'city' => 'Jena'], ['code' => 'JE', 'city' => 'Jerichower Land'],
            ['code' => 'JL', 'city' => 'Jerichower Land'],
            ['code' => 'K', 'city' => 'Köln'], ['code' => 'KA', 'city' => 'Karlsruhe'],
            ['code' => 'KAL', 'city' => 'Kall'], ['code' => 'KAR', 'city' => 'Karlsruhe'],
            ['code' => 'KB', 'city' => 'Waldeck-Frankenberg'], ['code' => 'KC', 'city' => 'Kronach'],
            ['code' => 'KE', 'city' => 'Kempten'], ['code' => 'KEH', 'city' => 'Kelheim'],
            ['code' => 'KEI', 'city' => 'Kehl'], ['code' => 'KEM', 'city' => 'Kemnath'],
            ['code' => 'KFB', 'city' => 'Kulmbach'], ['code' => 'KG', 'city' => 'Bad Kissingen'],
            ['code' => 'KH', 'city' => 'Bad Kreuznach'], ['code' => 'KI', 'city' => 'Kiel'],
            ['code' => 'KIB', 'city' => 'Donnersbergkreis'], ['code' => 'KL', 'city' => 'Kaiserslautern'],
            ['code' => 'KLZ', 'city' => 'Klötze'], ['code' => 'KN', 'city' => 'Konstanz'],
            ['code' => 'KNS', 'city' => 'Konstanz'], ['code' => 'KO', 'city' => 'Koblenz'],
            ['code' => 'KOR', 'city' => 'Rems-Murr-Kreis'], ['code' => 'KRU', 'city' => 'Ortenaukreis'],
            ['code' => 'KS', 'city' => 'Kassel'], ['code' => 'KSE', 'city' => 'Stendal'],
            ['code' => 'KT', 'city' => 'Kitzingen'], ['code' => 'KU', 'city' => 'Kulmbach'],
            ['code' => 'KÜN', 'city' => 'Hohenlohekreis'], ['code' => 'KUS', 'city' => 'Kusel'],
            ['code' => 'KYF', 'city' => 'Kyffhäuserkreis'],
            ['code' => 'L', 'city' => 'Leipzig'], ['code' => 'LA', 'city' => 'Landshut'],
            ['code' => 'LAU', 'city' => 'Nürnberger Land'], ['code' => 'LB', 'city' => 'Ludwigsburg'],
            ['code' => 'LBS', 'city' => 'Lebus'], ['code' => 'LBZ', 'city' => 'Ludwigslust'],
            ['code' => 'LC', 'city' => 'Elbe-Elster'], ['code' => 'LCH', 'city' => 'Lichtenfels'],
            ['code' => 'LE', 'city' => 'Esslingen'], ['code' => 'LEO', 'city' => 'Leonberg'],
            ['code' => 'LEV', 'city' => 'Leverkusen'], ['code' => 'LG', 'city' => 'Lüneburg'],
            ['code' => 'LGN', 'city' => 'Lüneburger Heide'], ['code' => 'LH', 'city' => 'Landsberg am Lech'],
            ['code' => 'LI', 'city' => 'Lindau'], ['code' => 'LIN', 'city' => 'Linden'],
            ['code' => 'LIP', 'city' => 'Lippe'], ['code' => 'LK', 'city' => 'Landkreis Kassel'],
            ['code' => 'LL', 'city' => 'Landsberg am Lech'], ['code' => 'LM', 'city' => 'Limburg-Weilburg'],
            ['code' => 'LN', 'city' => 'Lahn-Dill-Kreis'], ['code' => 'LNK', 'city' => 'Landkreis Kassel'],
            ['code' => 'LOS', 'city' => 'Oder-Spree'], ['code' => 'LPP', 'city' => 'Lippe'],
            ['code' => 'LR', 'city' => 'Rottweil'], ['code' => 'LRO', 'city' => 'Rostock'],
            ['code' => 'LÜN', 'city' => 'Lünen'], ['code' => 'LUP', 'city' => 'Ludwigslust-Parchim'],
            ['code' => 'LWL', 'city' => 'Landkreis Ludwigsburg'],
            ['code' => 'M', 'city' => 'München'], ['code' => 'MA', 'city' => 'Mannheim'],
            ['code' => 'MAB', 'city' => 'Mansfeld-Südharz'], ['code' => 'MAI', 'city' => 'Mainburg'],
            ['code' => 'MAK', 'city' => 'Maisach'], ['code' => 'MAL', 'city' => 'Mallersdorf'],
            ['code' => 'MAR', 'city' => 'Marburg-Biedenkopf'], ['code' => 'MAT', 'city' => 'Mayen-Koblenz'],
            ['code' => 'MAZ', 'city' => 'Mainz-Bingen'], ['code' => 'MB', 'city' => 'Miesbach'],
            ['code' => 'MBE', 'city' => 'Bayreuth'], ['code' => 'MBR', 'city' => 'Bremerhaven'],
            ['code' => 'MC', 'city' => 'Mittelsachsen'], ['code' => 'MCH', 'city' => 'Main-Tauber-Kreis'],
            ['code' => 'MD', 'city' => 'Magdeburg'], ['code' => 'MDK', 'city' => 'Dachau'],
            ['code' => 'ME', 'city' => 'Mettmann'], ['code' => 'MEI', 'city' => 'Meißen'],
            ['code' => 'MEK', 'city' => 'Mittlerer Erzgebirgskreis'], ['code' => 'MEL', 'city' => 'Melle'],
            ['code' => 'MER', 'city' => 'Merseburg'], ['code' => 'MGN', 'city' => 'Schmalkalden-Meiningen'],
            ['code' => 'MH', 'city' => 'Mühlheim an der Ruhr'], ['code' => 'MHM', 'city' => 'Mülheim'],
            ['code' => 'MI', 'city' => 'Minden-Lübbecke'], ['code' => 'MIL', 'city' => 'Miltenberg'],
            ['code' => 'MIS', 'city' => 'Miesbach'], ['code' => 'MIW', 'city' => 'Wittmund'],
            ['code' => 'MK', 'city' => 'Märkischer Kreis'], ['code' => 'MKK', 'city' => 'Main-Kinzig-Kreis'],
            ['code' => 'ML', 'city' => 'Mansfeld-Südharz'], ['code' => 'MLN', 'city' => 'Lörrach'],
            ['code' => 'MM', 'city' => 'Memmingen'], ['code' => 'MN', 'city' => 'Unterallgäu'],
            ['code' => 'MND', 'city' => 'Landau in der Pfalz'], ['code' => 'MNS', 'city' => 'Münster'],
            ['code' => 'MO', 'city' => 'Moers'], ['code' => 'MOD', 'city' => 'Modebach'],
            ['code' => 'MOL', 'city' => 'Märkisch-Oderland'], ['code' => 'MOR', 'city' => 'Morbach'],
            ['code' => 'MOS', 'city' => 'Konstanz'], ['code' => 'MOS', 'city' => 'Konstanz'],
            ['code' => 'MOT', 'city' => 'Motorkreis'], ['code' => 'MR', 'city' => 'Marburg-Biedenkopf'],
            ['code' => 'MRN', 'city' => 'Neckar-Odenwald-Kreis'], ['code' => 'MS', 'city' => 'Münster'],
            ['code' => 'MSE', 'city' => 'Mecklenburgische Seenplatte'], ['code' => 'MSP', 'city' => 'Main-Spessart'],
            ['code' => 'MST', 'city' => 'Mecklenburg-Strelitz'], ['code' => 'MTK', 'city' => 'Main-Taunus-Kreis'],
            ['code' => 'MTL', 'city' => 'Muldentalkreis'], ['code' => 'MTO', 'city' => 'Motorkreis'],
            ['code' => 'MÜ', 'city' => 'Mühldorf a. Inn'], ['code' => 'MÜB', 'city' => 'Mühlberg'],
            ['code' => 'MÜL', 'city' => 'Mühlhausen'], ['code' => 'MÜN', 'city' => 'Münster'],
            ['code' => 'MUR', 'city' => 'Murr'], ['code' => 'MW', 'city' => 'Mittweida'],
            ['code' => 'MY', 'city' => 'Mayen-Koblenz'], ['code' => 'MYK', 'city' => 'Mayen-Koblenz'],
            ['code' => 'MZG', 'city' => 'Merzig-Wadern'],
            ['code' => 'N', 'city' => 'Nürnberg'], ['code' => 'NA', 'city' => 'Nahe'],
            ['code' => 'NAB', 'city' => 'Nabburg'], ['code' => 'NAI', 'city' => 'Naila'],
            ['code' => 'NAW', 'city' => 'Neustadt an der Weinstraße'], ['code' => 'NB', 'city' => 'Neubrandenburg'],
            ['code' => 'ND', 'city' => 'Neuburg-Schrobenhausen'], ['code' => 'NDA', 'city' => 'Neustadt/Aisch'],
            ['code' => 'NDN', 'city' => 'Neustadt an der Donau'], ['code' => 'NE', 'city' => 'Neuss'],
            ['code' => 'NEA', 'city' => 'Neustadt/Aisch-Bad Windsheim'], ['code' => 'NEC', 'city' => 'Lahn-Dill-Kreis'],
            ['code' => 'NEL', 'city' => 'Mecklenburgische Seenplatte'], ['code' => 'NES', 'city' => 'Rhön-Grabfeld'],
            ['code' => 'NEU', 'city' => 'Neumarkt i.d.OPf.'], ['code' => 'NEW', 'city' => 'Neustadt a.d.Waldnaab'],
            ['code' => 'NF', 'city' => 'Nordfriesland'], ['code' => 'NK', 'city' => 'Neunkirchen'],
            ['code' => 'NL', 'city' => 'Northeim'], ['code' => 'NM', 'city' => 'Neumarkt i.d.OPf.'],
            ['code' => 'NMS', 'city' => 'Neumünster'], ['code' => 'NO', 'city' => 'Nordhorn'],
            ['code' => 'NOH', 'city' => 'Grafschaft Bentheim'], ['code' => 'NOL', 'city' => 'Görlitz'],
            ['code' => 'NOM', 'city' => 'Northeim'], ['code' => 'NOR', 'city' => 'Nordfriesland'],
            ['code' => 'NOS', 'city' => 'Osnabrück'], ['code' => 'NRW', 'city' => 'Neustadt a.Rbge.'],
            ['code' => 'NU', 'city' => 'Neu-Ulm'], ['code' => 'NÜ', 'city' => 'Nürnberger Land'],
            ['code' => 'NVP', 'city' => 'Vorpommern-Rügen'], ['code' => 'NW', 'city' => 'Neustadt an der Weinstraße'],
            ['code' => 'NWM', 'city' => 'Nordwestmecklenburg'], ['code' => 'NY', 'city' => 'Nürnberg'],
            ['code' => 'NZ', 'city' => 'Stendal'],
            ['code' => 'OA', 'city' => 'Oberallgäu'], ['code' => 'OAL', 'city' => 'Ostallgäu'],
            ['code' => 'OB', 'city' => 'Oberhausen'], ['code' => 'OBN', 'city' => 'Oberbergischer Kreis'],
            ['code' => 'OBT', 'city' => 'Bayreuth'], ['code' => 'OC', 'city' => 'Osterode am Harz'],
            ['code' => 'OD', 'city' => 'Stormarn'], ['code' => 'OE', 'city' => 'Olpe'],
            ['code' => 'OF', 'city' => 'Offenbach'], ['code' => 'OFT', 'city' => 'Offenburg'],
            ['code' => 'OG', 'city' => 'Ortenaukreis'], ['code' => 'OHA', 'city' => 'Osterode am Harz'],
            ['code' => 'OHV', 'city' => 'Oberhavel'], ['code' => 'OHZ', 'city' => 'Osterholz'],
            ['code' => 'OK', 'city' => 'Börde'], ['code' => 'OL', 'city' => 'Oldenburg'],
            ['code' => 'OLS', 'city' => 'Oldenburg'], ['code' => 'OP', 'city' => 'Oldenburg'],
            ['code' => 'OPP', 'city' => 'Amberg-Sulzbach'], ['code' => 'OR', 'city' => 'Oberspreewald-Lausitz'],
            ['code' => 'ORB', 'city' => 'Gelnhausen'], ['code' => 'ORT', 'city' => 'Ortenaukreis'],
            ['code' => 'OS', 'city' => 'Osnabrück'], ['code' => 'OSD', 'city' => 'Osnabrück'],
            ['code' => 'OSL', 'city' => 'Oberspreewald-Lausitz'], ['code' => 'OVP', 'city' => 'Vorpommern-Greifswald'],
            ['code' => 'OW', 'city' => 'Waldeck-Frankenberg'],
            ['code' => 'P', 'city' => 'Potsdam'], ['code' => 'PA', 'city' => 'Passau'],
            ['code' => 'PAN', 'city' => 'Rottal-Inn'], ['code' => 'PB', 'city' => 'Paderborn'],
            ['code' => 'PE', 'city' => 'Peine'], ['code' => 'PEG', 'city' => 'Pegnitz'],
            ['code' => 'PER', 'city' => 'Perlesreut'], ['code' => 'PF', 'city' => 'Pforzheim'],
            ['code' => 'PGN', 'city' => 'Pfaffenhofen'], ['code' => 'PIR', 'city' => 'Sächsische Schweiz-Osterzgebirge'],
            ['code' => 'PL', 'city' => 'Plauen'], ['code' => 'PLN', 'city' => 'Plön'],
            ['code' => 'PLÖ', 'city' => 'Plön'], ['code' => 'PM', 'city' => 'Potsdam-Mittelmark'],
            ['code' => 'PN', 'city' => 'Pfaffenhofen a.d.Ilm'], ['code' => 'PO', 'city' => 'Potsdam'],
            ['code' => 'PR', 'city' => 'Prignitz'], ['code' => 'PRN', 'city' => 'Prignitz'],
            ['code' => 'PRZ', 'city' => 'Prignitz'], ['code' => 'PS', 'city' => 'Kaiserslautern'],
            ['code' => 'PW', 'city' => 'Parchim'], ['code' => 'PZ', 'city' => 'Pirmasens'],
            ['code' => 'R', 'city' => 'Regensburg'], ['code' => 'RA', 'city' => 'Rastatt'],
            ['code' => 'RC', 'city' => 'Regen'], ['code' => 'RCB', 'city' => 'Regensburg'],
            ['code' => 'RE', 'city' => 'Recklinghausen'], ['code' => 'REG', 'city' => 'Regen'],
            ['code' => 'REI', 'city' => 'Berchtesgadener Land'], ['code' => 'REK', 'city' => 'Recklinghausen'],
            ['code' => 'REM', 'city' => 'Rems-Murr-Kreis'], ['code' => 'REN', 'city' => 'Rhein-Erft-Kreis'],
            ['code' => 'REU', 'city' => 'Reutlingen'], ['code' => 'REW', 'city' => 'Rhein-Erft-Kreis'],
            ['code' => 'RG', 'city' => 'Regensburg'], ['code' => 'RGE', 'city' => 'Regen'],
            ['code' => 'RH', 'city' => 'Roth'], ['code' => 'RHK', 'city' => 'Rhein-Hunsrück-Kreis'],
            ['code' => 'RHN', 'city' => 'Rhein-Neckar-Kreis'], ['code' => 'RI', 'city' => 'Riesa'],
            ['code' => 'RID', 'city' => 'Riedenburg'], ['code' => 'RIE', 'city' => 'Rieden'],
            ['code' => 'RL', 'city' => 'Rotenburg (Wümme)'], ['code' => 'RLK', 'city' => 'Rhein-Lahn-Kreis'],
            ['code' => 'RM', 'city' => 'Rhein-Maas-Kreis'], ['code' => 'RN', 'city' => 'Rennsteig'],
            ['code' => 'RND', 'city' => 'Region Hannover'], ['code' => 'RO', 'city' => 'Rosenheim'],
            ['code' => 'ROK', 'city' => 'Rockenhausen'], ['code' => 'ROS', 'city' => 'Rostock'],
            ['code' => 'ROT', 'city' => 'Roth'], ['code' => 'ROW', 'city' => 'Rotenburg (Wümme)'],
            ['code' => 'RP', 'city' => 'Rheinisch-Pfälzischer Kreis'], ['code' => 'RPP', 'city' => 'Rhein-Pfalz-Kreis'],
            ['code' => 'RS', 'city' => 'Remscheid'], ['code' => 'RT', 'city' => 'Reutlingen'],
            ['code' => 'RÜD', 'city' => 'Rheingau-Taunus-Kreis'], ['code' => 'RÜG', 'city' => 'Rügen'],
            ['code' => 'RUP', 'city' => 'Rügen'], ['code' => 'RW', 'city' => 'Rottweil'],
            ['code' => 'RWT', 'city' => 'Rottweil'], ['code' => 'RZ', 'city' => 'Herzogtum Lauenburg'],
            ['code' => 'S', 'city' => 'Stuttgart'], ['code' => 'SAD', 'city' => 'Schwandorf'],
            ['code' => 'SAL', 'city' => 'Regionalverband Saarbrücken'], ['code' => 'SAW', 'city' => 'Altmarkkreis Salzwedel'],
            ['code' => 'SB', 'city' => 'Saarbrücken'], ['code' => 'SBK', 'city' => 'Schönebeck'],
            ['code' => 'SC', 'city' => 'Schwabach'], ['code' => 'SCH', 'city' => 'Schwäbisch Hall'],
            ['code' => 'SDH', 'city' => 'Nordhausen'], ['code' => 'SDN', 'city' => 'Straubing'],
            ['code' => 'SE', 'city' => 'Segeberg'], ['code' => 'SEG', 'city' => 'Segeberg'],
            ['code' => 'SEI', 'city' => 'Saarpfalz-Kreis'], ['code' => 'SEL', 'city' => 'Selb'],
            ['code' => 'SEM', 'city' => 'Seelow'], ['code' => 'SET', 'city' => 'Seto'],
            ['code' => 'SGH', 'city' => 'Schaumburg'], ['code' => 'SHA', 'city' => 'Schwäbisch Hall'],
            ['code' => 'SHG', 'city' => 'Schaumburg'], ['code' => 'SI', 'city' => 'Siegen-Wittgenstein'],
            ['code' => 'SIE', 'city' => 'Siegen-Wittgenstein'], ['code' => 'SIG', 'city' => 'Sigmaringen'],
            ['code' => 'SIM', 'city' => 'Rhein-Hunsrück-Kreis'], ['code' => 'SK', 'city' => 'Sächsische Schweiz-Osterzgebirge'],
            ['code' => 'SKW', 'city' => 'Schwarzwald-Baar-Kreis'], ['code' => 'SL', 'city' => 'Schleswig-Flensburg'],
            ['code' => 'SLE', 'city' => 'Schleswig-Flensburg'], ['code' => 'SLF', 'city' => 'Saalfeld-Rudolstadt'],
            ['code' => 'SLK', 'city' => 'Salzlandkreis'], ['code' => 'SLN', 'city' => 'Saalfeld'],
            ['code' => 'SLS', 'city' => 'Saarlouis'], ['code' => 'SLZ', 'city' => 'Bad Salzungen'],
            ['code' => 'SM', 'city' => 'Schmalkalden-Meiningen'], ['code' => 'SMK', 'city' => 'Schmalkalden'],
            ['code' => 'SN', 'city' => 'Schwerin'], ['code' => 'SNK', 'city' => 'Schwabach'],
            ['code' => 'SO', 'city' => 'Soest'], ['code' => 'SOG', 'city' => 'Sonneberg'],
            ['code' => 'SON', 'city' => 'Sonneberg'], ['code' => 'SPD', 'city' => 'Spandau'],
            ['code' => 'SPK', 'city' => 'Kaiserslautern'], ['code' => 'SPM', 'city' => 'Spree-Neiße'],
            ['code' => 'SPW', 'city' => 'Speyer'], ['code' => 'SPN', 'city' => 'Spree-Neiße'],
            ['code' => 'SPW', 'city' => 'Speyer'], ['code' => 'SPW', 'city' => 'Speyer'],
            ['code' => 'SR', 'city' => 'Straubing'], ['code' => 'SRB', 'city' => 'Straubing-Bogen'],
            ['code' => 'ST', 'city' => 'Steinfurt'], ['code' => 'STB', 'city' => 'Saalfeld-Rudolstadt'],
            ['code' => 'STD', 'city' => 'Stade'], ['code' => 'STE', 'city' => 'Stendal'],
            ['code' => 'STL', 'city' => 'Steinlach'], ['code' => 'STN', 'city' => 'Starnberg'],
            ['code' => 'STO', 'city' => 'Stockach'], ['code' => 'STR', 'city' => 'Straubing'],
            ['code' => 'STS', 'city' => 'Starnberg'], ['code' => 'STW', 'city' => 'Strausberg'],
            ['code' => 'STZ', 'city' => 'Stollberg'],
            ['code' => 'SÜ', 'city' => 'Südhessen'],
            ['code' => 'SÜL', 'city' => 'Südliches Anhalt'], ['code' => 'SÜN', 'city' => 'Südniedersachsen'],
            ['code' => 'SÜW', 'city' => 'Südliche Weinstraße'], ['code' => 'SW', 'city' => 'Schweinfurt'],
            ['code' => 'SWK', 'city' => 'Sömmerdaer Landkreis'], ['code' => 'SWT', 'city' => 'Saale-Orla-Kreis'],
            ['code' => 'SY', 'city' => 'Nordhausen'], ['code' => 'SYK', 'city' => 'Syrau'],
            ['code' => 'SZB', 'city' => 'Salzgitter'],
            ['code' => 'TA', 'city' => 'Darmstadt-Dieburg'], ['code' => 'TAL', 'city' => 'Talheim'],
            ['code' => 'TBB', 'city' => 'Main-Tauber-Kreis'], ['code' => 'TE', 'city' => 'Teltow-Fläming'],
            ['code' => 'TF', 'city' => 'Teltow-Fläming'], ['code' => 'TG', 'city' => 'Traunstein'],
            ['code' => 'TGN', 'city' => 'Traunstein'], ['code' => 'TIR', 'city' => 'Tirschenreuth'],
            ['code' => 'TK', 'city' => 'Torgau-Oschatz'], ['code' => 'TKS', 'city' => 'Torgau'],
            ['code' => 'TL', 'city' => 'Tecklenburg'], ['code' => 'TLS', 'city' => 'Teltow-Fläming'],
            ['code' => 'TN', 'city' => 'Tuttlingen'], ['code' => 'TO', 'city' => 'Torgau'],
            ['code' => 'TÖL', 'city' => 'Bad Tölz-Wolfratshausen'], ['code' => 'TR', 'city' => 'Trier'],
            ['code' => 'TRE', 'city' => 'Trier'], ['code' => 'TRS', 'city' => 'Trier-Saarburg'],
            ['code' => 'TS', 'city' => 'Traunstein'], ['code' => 'TST', 'city' => 'Traunstein'],
            ['code' => 'TÜ', 'city' => 'Tübingen'], ['code' => 'TUT', 'city' => 'Tuttlingen'],
            ['code' => 'TW', 'city' => 'Teltow-Fläming'],
            ['code' => 'UE', 'city' => 'Uelzen'], ['code' => 'ÜB', 'city' => 'Überlingen'],
            ['code' => 'UCK', 'city' => 'Uckermark'], ['code' => 'UDA', 'city' => 'Udenhain'],
            ['code' => 'UE', 'city' => 'Uelzen'], ['code' => 'UEN', 'city' => 'Uelzen'],
            ['code' => 'UER', 'city' => 'Uckermark'], ['code' => 'UES', 'city' => 'Uelzen'],
            ['code' => 'UL', 'city' => 'Ulm'], ['code' => 'ULM', 'city' => 'Ulm'],
            ['code' => 'UM', 'city' => 'Uckermark'], ['code' => 'UN', 'city' => 'Unna'],
            ['code' => 'UST', 'city' => 'Ostvorpommern'], ['code' => 'UW', 'city' => 'Unterweser'],
            ['code' => 'V', 'city' => 'Vogtlandkreis'], ['code' => 'VB', 'city' => 'Vogelsbergkreis'],
            ['code' => 'VEC', 'city' => 'Vechta'], ['code' => 'VER', 'city' => 'Verden'],
            ['code' => 'VG', 'city' => 'Vorpommern-Greifswald'], ['code' => 'VIE', 'city' => 'Viersen'],
            ['code' => 'VK', 'city' => 'Völklingen'], ['code' => 'VKL', 'city' => 'Vogtland'],
            ['code' => 'VL', 'city' => 'Vechta'], ['code' => 'VLP', 'city' => 'Löwenberg'],
            ['code' => 'VME', 'city' => 'Vorpommern-Mecklenburg'], ['code' => 'VN', 'city' => 'Velbert'],
            ['code' => 'VO', 'city' => 'Vogtlandkreis'], ['code' => 'VOG', 'city' => 'Vogtlandkreis'],
            ['code' => 'VOR', 'city' => 'Vorpommern'], ['code' => 'VP', 'city' => 'Vorpommern-Rügen'],
            ['code' => 'VPK', 'city' => 'Vorpommern'], ['code' => 'VR', 'city' => 'Vorpommern-Rügen'],
            ['code' => 'VRN', 'city' => 'Rhein-Neckar-Kreis'], ['code' => 'VS', 'city' => 'Schwarzwald-Baar-Kreis'],
            ['code' => 'VUL', 'city' => 'Ulm'], ['code' => 'VW', 'city' => 'Wittenberg'],
            ['code' => 'W', 'city' => 'Wuppertal'], ['code' => 'WA', 'city' => 'Waldeck-Frankenberg'],
            ['code' => 'WAF', 'city' => 'Warendorf'], ['code' => 'WAK', 'city' => 'Wartburgkreis'],
            ['code' => 'WAN', 'city' => 'Wanne-Eickel'], ['code' => 'WAR', 'city' => 'Warendorf'],
            ['code' => 'WB', 'city' => 'Lutherstadt Wittenberg'], ['code' => 'WBK', 'city' => 'Weißenburg-Gunzenhausen'],
            ['code' => 'WEN', 'city' => 'Weiden i.d.OPf.'], ['code' => 'WES', 'city' => 'Wesel'],
            ['code' => 'WEW', 'city' => 'Westerwald'], ['code' => 'WF', 'city' => 'Wolfenbüttel'],
            ['code' => 'WGN', 'city' => 'Waldnaab'], ['code' => 'WHV', 'city' => 'Wilhelmshaven'],
            ['code' => 'WI', 'city' => 'Wiesbaden'], ['code' => 'WIL', 'city' => 'Bernkastel-Wittlich'],
            ['code' => 'WIN', 'city' => 'Winsener Aue'], ['code' => 'WIS', 'city' => 'Wismar'],
            ['code' => 'WIT', 'city' => 'Witten'], ['code' => 'WIZ', 'city' => 'Witzenhausen'],
            ['code' => 'WK', 'city' => 'Wittstock'], ['code' => 'WKS', 'city' => 'Wittstock'],
            ['code' => 'WL', 'city' => 'Harburg'], ['code' => 'WLG', 'city' => 'Wolfratshausen'],
            ['code' => 'WM', 'city' => 'Weilheim-Schongau'], ['code' => 'WMK', 'city' => 'Werra-Meißner-Kreis'],
            ['code' => 'WMS', 'city' => 'Landkreis Wolfsburg'], ['code' => 'WN', 'city' => 'Rems-Murr-Kreis'],
            ['code' => 'WNA', 'city' => 'Wunsiedel i.Fichtelgebirge'], ['code' => 'WND', 'city' => 'Neunkirchen'],
            ['code' => 'WNK', 'city' => 'Wunsiedel i.Fichtelgebirge'], ['code' => 'WO', 'city' => 'Worms'],
            ['code' => 'WOB', 'city' => 'Wolfsburg'], ['code' => 'WOG', 'city' => 'Vogelsbergkreis'],
            ['code' => 'WOR', 'city' => 'Worms'], ['code' => 'WOS', 'city' => 'Worms'],
            ['code' => 'WOS', 'city' => 'Worms'], ['code' => 'WOT', 'city' => 'Wolfratshausen'],
            ['code' => 'WOW', 'city' => 'Wolfsburg'], ['code' => 'WP', 'city' => 'Westerwald'],
            ['code' => 'WS', 'city' => 'Schwarzwald-Baar-Kreis'], ['code' => 'WT', 'city' => 'Waldshut'],
            ['code' => 'WTM', 'city' => 'Wittmund'], ['code' => 'WUG', 'city' => 'Weißenburg-Gunzenhausen'],
            ['code' => 'WUK', 'city' => 'Wunsiedel'], ['code' => 'WÜ', 'city' => 'Würzburg'],
            ['code' => 'WUL', 'city' => 'Wunsiedel'], ['code' => 'WUR', 'city' => 'Würzburg'],
            ['code' => 'WW', 'city' => 'Westerwald'], ['code' => 'WX', 'city' => 'Wittstock'],
            ['code' => 'WYN', 'city' => 'Wunsiedel'],
            ['code' => 'X', 'city' => 'Cottbus-Forst'], ['code' => 'XA', 'city' => 'Xanten'],
            ['code' => 'XAL', 'city' => 'Xanten'], ['code' => 'XK', 'city' => 'Xanten'],
            ['code' => 'Y', 'city' => 'Bayreuth'], ['code' => 'YAG', 'city' => 'Ansbach'],
            ['code' => 'Z', 'city' => 'Zwickau'], ['code' => 'ZE', 'city' => 'Anhalt-Zerbst'],
            ['code' => 'ZI', 'city' => 'Zittau'], ['code' => 'ZIG', 'city' => 'Zwickau'],
            ['code' => 'ZP', 'city' => 'Zwickau'], ['code' => 'ZR', 'city' => 'Zwickau'],
            ['code' => 'ZW', 'city' => 'Zweibrücken'], ['code' => 'ZWT', 'city' => 'Zwickau'],
            ['code' => 'GÖ', 'city' => 'Göttingen'], ['code' => 'KÖ', 'city' => 'Köln'],
            ['code' => 'MÖ', 'city' => 'Möckern'], ['code' => 'SÖ', 'city' => 'Sömmerdaer Land'],
        ];

        return response()->json(['success' => true, 'data' => $codes]);
    }
}
