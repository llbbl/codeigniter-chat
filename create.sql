-- Generation Time: Jul 22, 2013 at 02:56 PM
-- Server version: 5.1.53
-- PHP Version: 5.3.13

CREATE SCHEMA cichat;
USE cichat;


SET SQL_MODE="NO_AUTO_VALUE_ON_ZERO";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;


-- --------------------------------------------------------

--
-- Table structure for table `ci_sessions`
--

CREATE TABLE IF NOT EXISTS `ci_sessions` (
  `session_id` VARCHAR(40) NOT NULL DEFAULT '0',
  `ip_address` VARCHAR(16) NOT NULL DEFAULT '0',
  `user_agent` VARCHAR(50) NOT NULL,
  `last_activity` INT(10) UNSIGNED NOT NULL DEFAULT '0',
  `session_data` TEXT,
  PRIMARY KEY (`session_id`)
) ENGINE=MYISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `messages`
--

CREATE TABLE IF NOT EXISTS `messages` (
  `id` INT(7) NOT NULL AUTO_INCREMENT,
  `user` VARCHAR(255) NOT NULL,
  `msg` TEXT NOT NULL,
  `time` INT(9) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MYISAM  DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;
