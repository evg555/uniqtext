<?php

//Загрузка классов
function autoLoader($name) {
	require 'classes/' . $name.'.php';
}

spl_autoload_register('autoLoader');
