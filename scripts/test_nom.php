<?php
$ctx=stream_context_create(['http'=>['timeout'=>3,'header'=>"User-Agent: NV/1.0\r\n"]]);
$nom=file_get_contents('https://nominatim.openstreetmap.org/search?q=cebu+consolacion+tugbongan&format=json&limit=1',false,$ctx);
print_r($nom);
