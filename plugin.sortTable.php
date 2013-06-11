<?php
/**
 * @mainpage Libreria per la gestione degli ordinamenti nelle tabelle
 * 
 * La libreria si interfaccia alla classe @a HtmlTable di mootools (http://mootools.net/docs/more/Interface/HtmlTable.Sort).
 * 
 * INSTALLAZIONE
 * ---------------
 * 1. Scaricare il file plugin.sortTable.php
 * 2. Copiare il file plugin.sortTable.php nella directory /lib/plugin.
 * 3. Copiare il file sort_table.css nella directory dell'applicazione che richiama la classe sortTable oppure nella directory css di gino.
 * 4. Copiare, se non presenti, i file immagine nella directory img di gino oppure dell'applicazione che utilizza la libreria, modificando nel caso i percorsi nel file css.
 * 
 * UTILIZZO
 * ---------------
 * Per attivare la libreria occorre includerla all'inizio del file:
 * @code
 * require_once(PLUGIN_DIR.OS.'plugin.sortTable.php');
 * @endcode
 * 
 * La funzione javascript sortTable() deve essere caricata prima della tabella o della chiamata ajax che costruisce la tabella. \n
 * Se i dati vengono paginati e la paginazione avviene con chiamate ajax, nel callback indicare la funzione javascript dell'ordinamento (in questo caso sortTable) e, nel caso, richiamare all'interno di questa funzione altre funzioni quale ad esempio @a updateTooltips.
 * 
 * ESEMPIO
 * ---------------
 * @code
 * foreach($items AS $item)
 * {
 *   $buffer_aj = '...';
 *   
 *   $records[] = array(
 *     $item['date'], 
 *     $item['name'], 
 *     array('value'=>$buffer_aj, 'class'=>"no_border no_bkg")
 *   );
 * }
 * 
 * $sortTable = new sortTable();
 * $buffer .= $sortTable->init(array('js_function'=>"updateTooltips();", 'sortIndex'=>1));
 * $buffer .= $sortTable->startTable(array(
 * _("Data"), 
 * _("Nome"), 
 * array('class'=>"no_border no_bkg")
 * ));
 * 
 * $buffer .= $sortTable->rows($records);
 * $buffer .= $sortTable->endTable();
 * @endcode
 * 
 * VARIANTI
 * ---------------
 * Nel caso in cui la tabella non venga paginata con una chiamata ajax si può utilizzare il javascript che segue, che lega alla tabella con id @a theTable la classe @a HtmlTable
 * @code
 * $buffer .= "<script type=\"text/javascript\">\n
 * window.addEvent('domready', function(){
 *   new HtmlTable($('theTable'), {
 *     sortable: true,
 *     zebra: true,
 *     classZebra: \"zebra\",
 *     classHeadSort:\"downArrow\",
 *     classHeadSortRev :\"upArrow\",
 *     classCellSort:\"focusedColumn\"
 *   });
 * });
 * </script>";
 * @endcode
 * 
 * Esempio di caricamento dei dati nel javascript
 * @code
 * <<<TEST
 * <script type="text/javascript">
 * var data = [1.7,3,4.2,4.4];
 * var table = new HtmlTable({
 *   headers: ['click me to sort']
 * });
 * 
 * table.inject(document.body, 'top');
 * 
 * data.each(function(d) {
 *   table.push([d]);
 * });
 * table.enableSort();
 * 
 * window.addEvent('domready', function() {
 *   $('sort_button').addEvent('click', function () {
 *     table.sort(0)
 *   });
 * });			
 * </script>
 * 
 * <input type="button" value="Sort!" id="sort_button">
 * TEST;
 * @endcode
 */
 
/**
 * @file plugin.sortTable.php
 * @brief Contiene la classe plugin_sortTable
 * 
 * @copyright 2013 Otto srl (http://www.opensource.org/licenses/mit-license.php) The MIT License
 * @author marco guidotti guidottim@gmail.com
 * @author abidibo abidibo@gmail.com
 */
 
 /**
 * @brief Classe per la gestione degli ordinamenti nelle tabelle
 * 
 * @copyright 2013 Otto srl (http://www.opensource.org/licenses/mit-license.php) The MIT License
 * @author marco guidotti guidottim@gmail.com
 * @author abidibo abidibo@gmail.com
 */
class plugin_sortTable {
		
	/**
	 * Directory del file CSS
	 * 
	 * @var string
	 */
	private $_css_dir;
	
	/**
	 * Nome del file CSS
	 * 
	 * @var string
	 */
	private $_css_file;
	
	/**
	 * Valore ID della tabella
	 * 
	 * @var string
	 */
	private $_table_id;
	
	/**
	 * Costruttore
	 * 
	 * @param array $options
	 *   array associativo di opzioni
	 *   - @b css_dir (string): directory del file css (percorso relativo)
	 *   - @b css_file (string): nome del file css
	 *   - @b table_id (string): valore ID della tabella
	 */
	function __construct($options=array()) {
		
		$this->_css_dir = gOpt('css_dir', $options, CSS_WWW);
		$this->_css_file = gOpt('css_file', $options, 'sort_table.css');
		$this->_table_id = gOpt('table_id', $options, 'theTable');
	}
	
	/**
	 * Inizializzazione dell'ordinamento
	 * 
	 * @param array $options
	 *   array associativo di opzioni
	 *   - @b js_function (string): nomi delle funzioni javascript da richiamare all'interno della classe javascript sortTable()
	 *   opzioni della classe mootools HtmlTable():
	 *   - @b sortable (boolean): abilita l'ordinamento (default 'true')
	 *   - @b sortIndex (integer): indice della colonna di ordinamento in avvio (default 0), impostare null se non si vuole ordinare in avvio
	 *   - @b sortReverse (boolean): if true, the initial sorted row will be sorted in reverse. Defaults to false.
	 *   - @b parsers (array): a mapping of parsers for each column of data. See section on parsers below.
	 *   - @b defaultParser (string): if no parsers are defined and they cannot be auto detected, which parser to use; defaults to 'string'
	 *   - @b classSortable (string): the class to add to the table when sorting is enabled; defaults to 'table-sortable'
	 *   - @b classHeadSort (string): the class to add to the th that has the current sort (applied when sort order is forward); defaults to 'table-th-sort'
	 *   - @b classHeadSortRev (string): the class to add to the th that has the current sort (applied when sort order is reverse); defaults to 'table-th-sort-rev',
	 *   - @b classNoSort (string): if a th has this class, it will not be sortable; defaults to 'table-th-nosort'
	 *   - @b classGroup (string): class applied to td elements when more than one has the same value; defaults to 'table-tr-group',
	 *   - @b classGroupHead (string): class applied to the first td in a group of td elements that have the same value; defaults to 'table-tr-group-head'
	 *   - @b classCellSort (string): the class applied to td elements that are in the current sorted column. defaults to 'table-td-sort'
	 *   - @b classSortSpan (string): the class applied to a span element injected into the th headers when sorting is enabled; useful for adding an arrow background for the sorted column to indicate the sort direction. defaults to 'table-th-sort-span'
	 *   - @b thSelector (string defaults to 'th'): the string selector used in delegating sort events.
	 * @return string
	 * 
	 * Per i valori delle opzioni della classe mootools HtmlTable() occorre tenere in considerazione che il tipo di valore è per un javascript, ovvero che se devo impostare un valore boolean devo scrivere ad esempio array('sortable'=>'true') o 'false', lo stesso vale per 'null'.
	 */
	public function init($options=array()) {
		
		$js_function = gOpt('js_function', $options, null);
		$sortable = gOpt('sortable', $options, 'true');
		$sortIndex = gOpt('sortIndex', $options, 0);
		
		$registry = registry::instance();
		$registry->addCss($this->_css_dir.'/'.$this->_css_file);
		
		$buffer = "
			<script type=\"text/javascript\">\n
			function sortTable(){
				";
		if($js_function)
			$buffer .= $js_function;
		$buffer .= "
				new HtmlTable($('".$this->_table_id."'), {
					sortable: $sortable,
					sortIndex: $sortIndex, 
					zebra: true,
					classZebra: \"zebra\",
					classHeadSort:\"downArrow\",
					classHeadSortRev :\"upArrow\",
					classCellSort:\"focusedColumn\"
				});
			}
			window.addEvent('domready', sortTable);
			</script>\n";
		
		return $buffer;
	}
	
	/**
	 * Intestazioni della tabella
	 * 
	 * @param array $items valori delle intestazioni
	 * @return string
	 * 
	 * E' possibile definire alcuni elementi del tag TH utilizzando un array al posto del valore da mostrare: \n
	 *   - @b value (string): valore da mostrare
	 *   - @b class (string): nome della classe
	 *   - @b width (string): larghezza
	 *   - @b other (string): altro nel tag
	 *   - @b sort (boolean): colonna ordinabile (classe css 'table-th-nosort'); in ogni caso non è ordinabile una colonna senza intestazione
	 */
	public function startTable($items=array()) {
		
		$buffer = "<table class=\"sortTable\" id=\"".$this->_table_id."\">";
		
		if(sizeof($items) > 0)
		{
			$buffer .= "<thead>";
			$buffer .= "<tr class=\"SortTableHeader\">";
			
			foreach($items AS $item)
			{
				if(is_array($item))
				{
					$value = gOpt('value', $item, null);
					$class = gOpt('class', $item, null);
					$width = gOpt('width', $item, null);
					$other = gOpt('other', $item, null);
					$sort = gOpt('sort', $item, null);
					
					if((is_bool($sort) && $sort === false) || !$value)
					{
						$class = $class ? ' '.$class : '';
						$class = "table-th-nosort".$class;
					}
				}
				else
				{
					$value = $item;
					$class = $width = $other = null;
				}
				
				$buffer .= "<th";
				if($class)
					$buffer .= " class=\"$class\"";
				if($width)
					$buffer .= " width=\"$width\"";
				if($other)
					$buffer .= " $other";
				
				$buffer .= ">".$value."</th>";
			}
			$buffer .= "</tr>";
			$buffer .= "</thead>";
		}
		$buffer .= "<tbody>";
		
		return $buffer;
	}
	
	/**
	 * Chiusura della tabella
	 * 
	 * @return string
	 */
	public function endTable() {
		
		$buffer = "</tbody>";
		$buffer .= "</table>";
		
		return $buffer;
	}
	
	/**
	 * Stampa gli elementi della tabella
	 * 
	 * @param array $items elementi della tabella
	 * @return string
	 * 
	 * Ogni record deve presentare i valori conformemente all'ordine delle colonne dell'intestazione. \n
	 * La chiave @a tr_data (array) permette di definire alcuni elementi del tag TR: \n
	 *   - @b id (string): valore ID del tag
	 *   - @b class (string): nome della classe
	 *   - @b other (string): altro nel tag
	 * 
	 * E' altresì possibile definire alcuni elementi del tag TD utilizzando un array al posto del valore da mostrare: \n
	 *   - @b value (string): valore da mostrare
	 *   - @b class (string): nome della classe
	 *   - @b width (string): larghezza
	 *   - @b other (string): altro nel tag
	 * 
	 * Esempio:
	 * @code
	 * $records[] = array(
	 *   'tr_data'=>array('id'=>'ID_val'), 
	 *   $date, 
	 *   $name, 
	 *   array('value'=>$buffer_aj, 'class'=>"no_border no_bkg")
	 * );
	 * @endcode
	 */
	public function rows($items) {
		
		$buffer = '';
		
		if(count($items))
		{
			foreach($items AS $item)
			{
				if(count($item))	// la riga è vuota?
				{
					$tr_data = gOpt('tr_data', $item, null);
					if(is_array($tr_data))
					{
						$tr_id = gOpt('id', $tr_data, null);
						$tr_class = gOpt('class', $tr_data, null);
						$tr_other = gOpt('other', $tr_data, null);
						
						$buffer .= "<tr";
						if($tr_id)
							$buffer .= " id=\"$tr_id\"";
						if($tr_class)
							$buffer .= " class=\"$tr_class\"";
						if($tr_other)
							$buffer .= " $tr_other";
						
						$buffer .= ">";
					}
					else 
					{
						$buffer .= "<tr>";
					}
					
					if($tr_data)
						unset($item['tr_data']);
					
					foreach($item AS $value)
					{
						if(is_array($value))
						{
							$td_value = gOpt('value', $value, null);
							$td_class = gOpt('class', $value, null);
							$td_width = gOpt('width', $value, null);
							$td_other = gOpt('other', $value, null);
						}
						else
						{
							$td_value = $value;
							$td_class = $td_width = $td_other = null;
						}
						$buffer .= "<td";
						if($td_class)
							$buffer .= " class=\"$td_class\"";
						if($td_width)
							$buffer .= " width=\"$td_width\"";
						if($td_other)
							$buffer .= " $td_other";
						
						$buffer .= ">".$td_value."</td>";
					}
					$buffer .= "</tr>";
				}
			}
		}
		return $buffer;
	}
}
?>