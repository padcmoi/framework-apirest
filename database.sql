-- phpMyAdmin SQL Dump
-- version 5.0.2
-- https://www.phpmyadmin.net/
--
-- Hôte : localhost
-- Généré le : sam. 01 août 2020 à 11:00
-- Version du serveur :  5.7.24
-- Version de PHP : 7.2.19

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de données : `__a_renseigner__`
--

-- --------------------------------------------------------

--
-- Structure de la table `aer_account`
--

CREATE TABLE `aer_account` (
  `id` int(11) NOT NULL,
  `enable` tinyint(1) NOT NULL DEFAULT '1',
  `user` varchar(30) NOT NULL,
  `password` varchar(128) NOT NULL DEFAULT '',
  `rank` enum('guest','user','operator','admin','root') NOT NULL DEFAULT 'user',
  `email` varchar(80) NOT NULL,
  `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_connected` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `guid` varchar(150) NOT NULL DEFAULT '00000000-0000-0000-0000-000000000000'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Structure de la table `aer_captcha`
--

CREATE TABLE `aer_captcha` (
  `remote_ip` varchar(80) NOT NULL DEFAULT '',
  `picture` blob,
  `code` varchar(10) NOT NULL DEFAULT '',
  `user_target` varchar(50) DEFAULT NULL,
  `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_test` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Structure de la table `aer_extra_info`
--

CREATE TABLE `aer_extra_info` (
  `sql_update` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `id_account` int(11) NOT NULL,
  `gender` enum('male','female') NOT NULL DEFAULT 'male',
  `firstName` varchar(50) NOT NULL DEFAULT '',
  `lastName` varchar(50) NOT NULL DEFAULT '',
  `phone` varchar(32) NOT NULL DEFAULT '',
  `age` date NOT NULL DEFAULT '1982-01-01',
  `adress` varchar(255) NOT NULL DEFAULT '',
  `citycode` varchar(10) NOT NULL DEFAULT '00000',
  `city` varchar(50) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Structure de la table `aer_internal_mail`
--

CREATE TABLE `aer_internal_mail` (
  `sql_update` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `id_account` int(11) NOT NULL,
  `can_expire` tinyint(1) NOT NULL DEFAULT '1',
  `hash_to_return` varchar(128) NOT NULL,
  `email` varchar(80) NOT NULL,
  `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `type` enum('recover_password','account_enable','change_email','close_account','recover_personnal_data') DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Structure de la table `aer_preventspam_ip`
--

CREATE TABLE `aer_preventspam_ip` (
  `sql_update` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `IP` varchar(80) NOT NULL,
  `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Structure de la table `aer_preventspam_mail`
--

CREATE TABLE `aer_preventspam_mail` (
  `sql_update` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `id_account` int(11) NOT NULL,
  `email` varchar(80) NOT NULL,
  `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Structure de la table `aer_session_active`
--

CREATE TABLE `aer_session_active` (
  `sql_update` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `id_account` int(11) NOT NULL,
  `loggedIn` tinyint(1) NOT NULL DEFAULT '0',
  `jwt_hash` varchar(32) NOT NULL,
  `last_access_data` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Index pour les tables déchargées
--

--
-- Index pour la table `aer_account`
--
ALTER TABLE `aer_account`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user` (`user`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Index pour la table `aer_captcha`
--
ALTER TABLE `aer_captcha`
  ADD UNIQUE KEY `remote_ip` (`remote_ip`);

--
-- Index pour la table `aer_extra_info`
--
ALTER TABLE `aer_extra_info`
  ADD UNIQUE KEY `id_account` (`id_account`);

--
-- Index pour la table `aer_internal_mail`
--
ALTER TABLE `aer_internal_mail`
  ADD UNIQUE KEY `id_account` (`id_account`),
  ADD UNIQUE KEY `hash_to_return` (`hash_to_return`);

--
-- Index pour la table `aer_preventspam_ip`
--
ALTER TABLE `aer_preventspam_ip`
  ADD UNIQUE KEY `IP` (`IP`);

--
-- Index pour la table `aer_preventspam_mail`
--
ALTER TABLE `aer_preventspam_mail`
  ADD UNIQUE KEY `id_account` (`id_account`);

--
-- Index pour la table `aer_session_active`
--
ALTER TABLE `aer_session_active`
  ADD UNIQUE KEY `id_account` (`id_account`);

--
-- AUTO_INCREMENT pour les tables déchargées
--

--
-- AUTO_INCREMENT pour la table `aer_account`
--
ALTER TABLE `aer_account`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4466;

--
-- Contraintes pour les tables déchargées
--

--
-- Contraintes pour la table `aer_extra_info`
--
ALTER TABLE `aer_extra_info`
  ADD CONSTRAINT `account_extra_info_related_id` FOREIGN KEY (`id_account`) REFERENCES `aer_account` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `aer_internal_mail`
--
ALTER TABLE `aer_internal_mail`
  ADD CONSTRAINT `account_internal_mail_related_id` FOREIGN KEY (`id_account`) REFERENCES `aer_account` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `aer_preventspam_mail`
--
ALTER TABLE `aer_preventspam_mail`
  ADD CONSTRAINT `account_mail_preventspam_related_id` FOREIGN KEY (`id_account`) REFERENCES `aer_account` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `aer_session_active`
--
ALTER TABLE `aer_session_active`
  ADD CONSTRAINT `account_session_active_related_id` FOREIGN KEY (`id_account`) REFERENCES `aer_account` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
