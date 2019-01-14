<?php
namespace Entheos\Utils\Lib;

use Cake\Utility\Xml;
use Cake\Core\Configure;

class FatturaElettronica {

	public $invoice = null;

	public function getParam($param)
	{
		return Configure::read('FatturaElettronica.'.$param);
	}

	/**
	 * Genera il file XML a partire dalla entity Invoice con contain InvoiceLines, Clients, ClientContacts
	 * Il builder non gestisce l'header del formato, per cui genero prima un XML in un elemento root generico,
	 * e poi recupero il contenuto mettendolo nei tag root di quello ufficiale
	 * @param  Invoice $invoice 
	 * @return void
	 */
	public function generate($invoice)
	{
		$this->invoice = $this->__normalizeInvoiceData($invoice);
		$base = $this->__base();
		$xml = Xml::build(['root' => [
			'FatturaElettronicaHeader' 	=> $this->__feHeader(),
			'FatturaElettronicaBody' 	=> $this->__feBody(),
		]]);
		// debug($xml);
		// debug($this->invoice);
		$body = $this->generateInnerNodes($xml);
		$fullXml = str_replace('%%BODY%%', $body, $base);
		return $fullXml;
	}

	private function __feHeader()
	{
		$data = [
			'DatiTrasmissione' => [
				'IdTrasmittente' => [
					'IdPaese' => 'IT',
					'IdCodice' => $this->getParam('TrasmissioneIdCodice'),
				],
				'ProgressivoInvio' => $this->invoice->progressivo_invio,
				'FormatoTrasmissione' => 'FPR12', // Verso Privati/Aziende, non PA
				'CodiceDestinatario' => $this->invoice->client->codice_destinatario_fe,
				'ContattiTrasmittente' => [],
				'PECDestinatario' => $this->invoice->client->pec, 
			],
			'CedentePrestatore' => [ // Fornitore
				'DatiAnagrafici' => [
					'IdFiscaleIVA' => [
						'IdPaese' => 'IT',
						'IdCodice' => $this->getParam('FornitoreIdCodice'),
					],
					'Anagrafica' => [
						'Denominazione' => $this->getParam('FornitoreDenominazione'),
					],
					'RegimeFiscale' => $this->getParam('FornitoreRegimeFiscale'),
				],
				'Sede' => $this->getParam('FornitoreSede')
			],
			'CessionarioCommittente' => [ // Cliente
				'DatiAnagrafici' => [
					'CodiceFiscale' => $this->invoice->client->codice_fiscale_fe,
					'Anagrafica' => [
						'Denominazione' => $this->invoice->client->ragione_sociale,
					],
				],
				'Sede' => [
					'Indirizzo' => $this->invoice->client->indirizzo,
					'CAP' => $this->invoice->client->cap,
					'Comune' => $this->invoice->client->citta,
					'Provincia' => $this->invoice->client->provincia,
					'Nazione' => 'IT',
				],
			],
		];

		if(empty($data['DatiTrasmissione']['PECDestinatario']))
			unset($data['DatiTrasmissione']['PECDestinatario']);

		return $data;
	}

	private function __feBody()
	{
		$data = [
			'DatiGenerali' => [
				'DatiGeneraliDocumento' => [
					'TipoDocumento' => $this->invoice->tipo_doc == 'FAT' ? 'TD01' : 'TD04', // FAT o NDC
					'Divisa' => 'EUR',
					'Data' => $this->invoice->data->format('Y-m-d'),
					'Numero' => $this->invoice->progressivo_invio,
					'DatiRitenuta' => null, // Placeholder
					'Causale' => str_split($this->invoice->causale, 200),
				],
			],
			'DatiBeniServizi' => [
				'DettaglioLinee' => $this->__getDettaglioLinee(),
				'DatiRiepilogo'  => $this->__getDatiRiepilogo(),
			],
			'DatiPagamento' => [
				'CondizioniPagamento' => 'TP02', // [TP01]: pagamento a rate [TP02]: pagamento completo [TP03]: anticipo
				'DettaglioPagamento' => [
					'ModalitaPagamento' => $this->invoice->mod_pagamento,
					'ImportoPagamento'  => $this->__toDecimal($this->invoice->netto_a_pagare),
					// 'DataScadenzaPagamento' => '2015-01-30',
				],
			],
		];

		if($this->invoice->ritenuta != 0)
		{
			$data['DatiGenerali']['DatiGeneraliDocumento']['DatiRitenuta'] = [
				'TipoRitenuta' => 'RT02', // Persone giuridiche
				'ImportoRitenuta' => $this->__toDecimal($this->invoice->ritenuta),
				'AliquotaRitenuta' => $this->__toDecimal($this->invoice->coefficiente_ritenuta / 100),
			];
		}
		else
			unset($data['DatiGenerali']['DatiGeneraliDocumento']['DatiRitenuta']);

		return $data;
	}

	private function __getDettaglioLinee()
	{
		$lines = [];
		$i = 1;
		foreach($this->invoice->invoice_lines as $r)
		{
			$t = [
				'NumeroLinea' => $i++,
				'Descrizione' => $r->descrizione,
				'Quantita' => $this->__toDecimal(1),
				'PrezzoUnitario' => $this->__toDecimal($r->importo),
				'PrezzoTotale' => $this->__toDecimal($r->importo),
				'AliquotaIVA' => $this->__toDecimal($r->vat_rate->aliquota),
			];

			if($this->invoice->ritenuta != 0)
				$t['Ritenuta'] = 'SI';

			if($r->vat_rate->cod_natura_no_iva){
				$t['Natura'] = $r->vat_rate->cod_natura_no_iva;
			}

			$lines[] = $t;
		}
		return $lines;
	}

	private function __getDatiRiepilogo()
	{
		$aliquote = [];
		foreach($this->invoice->aliquote as $r)
		{
			$t = [
				'AliquotaIVA' => $this->__toDecimal($r['aliquota']),
			];

			if($r['cod_natura_no_iva'])
				$t += ['Natura' => $r['cod_natura_no_iva']];

			$t += [
				'ImponibileImporto' => $this->__toDecimal($r['imponibile']),
				'Imposta' => $this->__toDecimal($r['iva']),
				'EsigibilitaIVA' => 'I',
			];

			if($r['cod_natura_no_iva'])
				$t += ['RiferimentoNormativo' => $r['dicitura_in_calce']];

			$aliquote[] = $t;
		}
		return $aliquote;
	}

	private function __toDecimal($v)
	{
		return number_format($v, 2, '.', '');
	}

	private function __normalizeInvoiceData($e)
	{
		$n = !empty($e->n_display) ? $e->n_display : $e->n;
		$e->progressivo_invio = str_pad(str_replace(['/'], '', $n), 8, '0', STR_PAD_LEFT);

		if(empty($e->causale))
			$e->causale = $e->invoice_lines[0]->descrizione;

		if($e->client->partita_iva)
			$e->client->codice_fiscale_fe = $e->client->partita_iva;
		else
			$e->client->codice_fiscale_fe = $e->client->codice_fiscale;

		if(empty($e->client->codice_destinatario_fe))
			$e->client->codice_destinatario_fe = '0000000'; // - ‘0000000’, nei casi di fattura destinata ad un soggetto per il quale non si conosce il canale telematico (PEC o altro) sul quale recapitare il file.

		if(!isset($e->client->pec))
			$e->client->pec = '';

		return $e;
	}

	private function __base()
	{
		$xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<?xml-stylesheet type="text/xsl" href="/api/fe.xsl"?>
<p:FatturaElettronica versione="FPR12" xmlns:ds="http://www.w3.org/2000/09/xmldsig#" xmlns:p="http://ivaservizi.agenziaentrate.gov.it/docs/xsd/fatture/v1.2" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://ivaservizi.agenziaentrate.gov.it/docs/xsd/fatture/v1.2 http://www.fatturapa.gov.it/export/fatturazione/sdi/fatturapa/v1.2/Schema_del_file_xml_FatturaPA_versione_1.2.xsd">
%%BODY%%</p:FatturaElettronica>
XML;
		return $xml;
	}

	private function generateInnerNodes($simpleXml)
	{
		return str_replace(['<?xml version="1.0" encoding="UTF-8"?>'."\n", '<root>', '</root>'], '', $simpleXml->asXML());
	}
}
