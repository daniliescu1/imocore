<?php

use App\Http\Controllers\BackupController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\AnexaController;
use App\Http\Controllers\CitireContorController;
use App\Http\Controllers\ConfigurareAnexaController;
use App\Http\Controllers\ContractController;
use App\Http\Controllers\ServiciuStandardAnexaController;
use App\Http\Controllers\ContabilitatePrimaraController;
use App\Http\Controllers\FacturaController;
use App\Http\Controllers\ImobilController;
use App\Http\Controllers\IndexareChiriiController;
use App\Http\Controllers\LocatorController;
use App\Http\Controllers\ReguliImobilController;
use App\Http\Controllers\SetariController;
use App\Http\Controllers\SimpleCrudController;
use App\Http\Controllers\PerioadaInchiriereFatadaController;
use App\Http\Controllers\SpatiuController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', DashboardController::class)->name('dashboard');
Route::get('/contabilitate-primara', [ContabilitatePrimaraController::class, 'index'])->name('contabilitate-primara.index');

Route::get('/imobile', [ImobilController::class, 'index'])->name('imobile.index');
Route::put('/imobile/reordonare', [ImobilController::class, 'reorder'])->name('imobile.reorder');
Route::get('/imobile/adauga', [ImobilController::class, 'create'])->name('imobile.create');
Route::post('/imobile', [ImobilController::class, 'store'])->name('imobile.store');
Route::get('/imobile/{imobil}/editare', [ImobilController::class, 'edit'])->name('imobile.edit');
Route::put('/imobile/{imobil}', [ImobilController::class, 'update'])->name('imobile.update');
Route::put('/imobile/{imobil}/configurari-anexe', [ImobilController::class, 'updateConfigurariAnexe'])->name('imobile.configurari-anexe.update');
Route::delete('/imobile/{imobil}', [ImobilController::class, 'destroy'])->name('imobile.destroy');
Route::get('/imobile/{imobil}/cf/{index}/vizualizare', [ImobilController::class, 'viewCfFile'])->name('imobile.cf.view');
Route::get('/imobile/{imobil}/cf/{index}/descarcare', [ImobilController::class, 'downloadCfFile'])->name('imobile.cf.download');
Route::get('/configurare-anexa', [ConfigurareAnexaController::class, 'index'])->name('configurare-anexa.index');
Route::put('/configurare-anexa/curs', [ConfigurareAnexaController::class, 'updateCurs'])->name('configurare-anexa.curs.update');
Route::put('/configurare-anexa/servicii-standard/pret/bulk', [ServiciuStandardAnexaController::class, 'updateBulkPreturi'])->name('configurare-anexa.servicii-standard.pret.bulk');
Route::get('/configurare-anexa/servicii-standard/{tip}', [ServiciuStandardAnexaController::class, 'index'])->name('configurare-anexa.servicii-standard.index');
Route::post('/configurare-anexa/servicii-standard/{tip}', [ServiciuStandardAnexaController::class, 'store'])->name('configurare-anexa.servicii-standard.store');
Route::put('/configurare-anexa/servicii-standard/{tip}/{serviciuStandard}', [ServiciuStandardAnexaController::class, 'update'])->name('configurare-anexa.servicii-standard.update');
Route::delete('/configurare-anexa/servicii-standard/{tip}/{serviciuStandard}', [ServiciuStandardAnexaController::class, 'destroy'])->name('configurare-anexa.servicii-standard.destroy');
Route::get('/configurare-anexa/adauga', [ConfigurareAnexaController::class, 'create'])->name('configurare-anexa.create');
Route::post('/configurare-anexa', [ConfigurareAnexaController::class, 'store'])->name('configurare-anexa.store');
Route::get('/configurare-anexa/{configurare}/editare', [ConfigurareAnexaController::class, 'edit'])->name('configurare-anexa.edit');
Route::put('/configurare-anexa/{configurare}', [ConfigurareAnexaController::class, 'update'])->name('configurare-anexa.update');
Route::put('/spatii/reordonare', [SpatiuController::class, 'reorder'])->name('spatii.reorder');
Route::get('/spatii', [SpatiuController::class, 'index'])->name('spatii.index');
Route::get('/spatii/adauga', [SpatiuController::class, 'create'])->name('spatii.create');
Route::post('/spatii', [SpatiuController::class, 'store'])->name('spatii.store');
Route::get('/spatii/{spatiu}/editare', [SpatiuController::class, 'edit'])->name('spatii.edit');
Route::patch('/spatii/{spatiu}/marcaj', [SpatiuController::class, 'updateMarcaj'])->name('spatii.marcaj');
Route::patch('/spatii/{spatiu}/anexa', [SpatiuController::class, 'updateAnexa'])->name('spatii.anexa');
Route::post('/spatii/{spatiu}/perioade-fatada', [PerioadaInchiriereFatadaController::class, 'store'])->name('spatii.perioade-fatada.store');
Route::put('/spatii/{spatiu}/perioade-fatada/{perioada}', [PerioadaInchiriereFatadaController::class, 'update'])->name('spatii.perioade-fatada.update');
Route::put('/spatii/{spatiu}', [SpatiuController::class, 'update'])->name('spatii.update');
Route::delete('/spatii/{spatiu}', [SpatiuController::class, 'destroy'])->name('spatii.destroy');
Route::post('/spatii/{spatiu}/anexa-individuala', [SpatiuController::class, 'cloneAnexaIndividuala'])->name('spatii.anexa-individuala');
Route::get('/locatori', [LocatorController::class, 'index'])->name('locatori.index');
Route::get('/locatori/adauga', [LocatorController::class, 'create'])->name('locatori.create');
Route::post('/locatori', [LocatorController::class, 'store'])->name('locatori.store');
Route::get('/locatori/{locator}/editare', [LocatorController::class, 'edit'])->name('locatori.edit');
Route::put('/locatori/{locator}', [LocatorController::class, 'update'])->name('locatori.update');
Route::get('/citiri-contoare', [CitireContorController::class, 'index'])->name('citiri-contoare.index');
Route::get('/citiri-contoare/imobil/{imobil}', [CitireContorController::class, 'imobil'])->name('citiri-contoare.imobil');
Route::post('/citiri-contoare', [CitireContorController::class, 'store'])->name('citiri-contoare.store');
Route::post('/citiri-contoare/inchide', [CitireContorController::class, 'inchide'])->name('citiri-contoare.inchide');
Route::get('/anexe', [AnexaController::class, 'index'])->name('anexe.index');
Route::post('/anexe/generare', [AnexaController::class, 'generate'])->name('anexe.generate');
Route::get('/anexe/imobil/{imobil}', [AnexaController::class, 'imobil'])->name('anexe.imobil');
Route::get('/anexe/{anexa}', [AnexaController::class, 'show'])->name('anexe.show');
Route::get('/anexe/{anexa}/descarcare', [AnexaController::class, 'download'])->name('anexe.download');
Route::delete('/anexe/{anexa}', [AnexaController::class, 'destroy'])->name('anexe.destroy');
Route::get('/contracte', [ContractController::class, 'index'])->name('contracte.index');
Route::get('/contracte/adauga', [ContractController::class, 'create'])->name('contracte.create');
Route::post('/contracte', [ContractController::class, 'store'])->name('contracte.store');
Route::get('/contracte/{contract}/editare', [ContractController::class, 'edit'])->name('contracte.edit');
Route::put('/contracte/{contract}', [ContractController::class, 'update'])->name('contracte.update');
Route::get('/facturare', [FacturaController::class, 'index'])->name('facturare.index');
Route::get('/facturare/imobil/{imobil}', [FacturaController::class, 'imobil'])->name('facturare.imobil');
Route::put('/facturare/curs', [FacturaController::class, 'updateCurs'])->name('facturare.curs.update');
Route::post('/facturare/generare', [FacturaController::class, 'generate'])->name('facturare.generate');
Route::get('/facturare/{factura}', [FacturaController::class, 'show'])->name('facturare.show');
Route::get('/facturare/{factura}/descarcare', [FacturaController::class, 'download'])->name('facturare.download');
Route::delete('/facturare/{factura}', [FacturaController::class, 'destroy'])->name('facturare.destroy');
Route::get('/reguli-imobile', [ReguliImobilController::class, 'index'])->name('reguli-imobile.index');
Route::put('/reguli-imobile/{imobil}', [ReguliImobilController::class, 'update'])->name('reguli-imobile.update');
Route::get('/setari', [SetariController::class, 'index'])->name('setari.index');
Route::get('/backup', [BackupController::class, 'index'])->name('backup.index');
Route::post('/backup', [BackupController::class, 'store'])->name('backup.store');
Route::get('/backup/spatii-toate', [BackupController::class, 'downloadAllSpatii'])->name('backup.download.spatii-toate');
Route::get('/backup/spatii-marcate', [BackupController::class, 'downloadMarcateSpatii'])->name('backup.download.spatii-marcate');
Route::get('/backup/spatii-fara-anexa', [BackupController::class, 'downloadFaraAnexaSpatii'])->name('backup.download.spatii-fara-anexa');
Route::get('/backup/spatii-fara-contract-activ', [BackupController::class, 'downloadFaraContractActivSpatii'])->name('backup.download.spatii-fara-contract-activ');
Route::get('/indexare-chirii', [IndexareChiriiController::class, 'index'])->name('indexare-chirii.index');
Route::patch('/indexare-chirii/{spatiu}', [IndexareChiriiController::class, 'update'])->name('indexare-chirii.update');
Route::get('/backup/{date}/{type}', [BackupController::class, 'download'])->name('backup.download');
Route::get('/backup/{date}/spatii/{file}', [BackupController::class, 'downloadSpatii'])->name('backup.download.spatii');

foreach (['rezervari', 'pv-predare', 'utilitati'] as $crudModule) {
    Route::get("/{$crudModule}", [SimpleCrudController::class, 'index'])->defaults('module', $crudModule);
    Route::get("/{$crudModule}/adauga", [SimpleCrudController::class, 'create'])->defaults('module', $crudModule);
    Route::post("/{$crudModule}", [SimpleCrudController::class, 'store'])->defaults('module', $crudModule);
    Route::get("/{$crudModule}/{id}/editare", [SimpleCrudController::class, 'edit'])->defaults('module', $crudModule);
    Route::put("/{$crudModule}/{id}", [SimpleCrudController::class, 'update'])->defaults('module', $crudModule);
}

$modules = [
    '/cheltuieli' => 'Cheltuieli',
    '/operr-app' => 'Operr App',
];

foreach ($modules as $uri => $module) {
    Route::get($uri, fn () => Inertia::render('ModulePage', ['module' => $module]));
}