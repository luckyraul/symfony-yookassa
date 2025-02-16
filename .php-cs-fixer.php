<?php
$finder = PhpCsFixer\Finder::create()->in('.');
$config = new \Mygento\Symfony\Config\Symfony();
$config->setFinder($finder);
return $config;