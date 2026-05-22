<?php

declare(strict_types=1);

namespace OpenMapsight\pulptic;

class CommonUtils
{
    public static function eventTypeCategoryCodeToString($code): string
    {
        /*
         *  1 LEVEL OF SERVICE                  Verkehrslage
         *  2 EXPECTED LEVEL OF SERVICE         Erwartete Verkehrslage
         *  3 ACCIDENTS                         Unfälle
         *  4 INCIDENTS                         Vorfälle
         *  5 CLOSURES AND LANE RESTRICTIONS    Straßen- und Fahrbahnsperrungen
         *  6 CARRIAGEWAY RESTRICTIONS          Fahrbahnbeschränkungen
         *  7 EXIT RESTRICTIONS                 Beschränkungen der Ausfahrt
         *  8 ENTRY RESTRICTIONS                Beschränkungen der Einfahrt
         *  9 TRAFFIC RESTRICTIONS              Verkehrsbeschränkungen
         * 10 CARPOOL INFORMATION               Informationen für Fahrgemeinschaften
         * 11 ROADWORKS                         Bauarbeiten
         * 12 OBSTRUCTION HAZARDS               Behinderungen auf der Fahrbahn
         * 13 DANGEROUS SITUATIONS              Gefährliche Situationen
         * 14 ROAD CONDITIONS                   Straßenzustand
         * 15 TEMPERATURES                      Temperaturen
         * 16 PRECIPITATION AND VISIBILITY      Niederschlag und Sichtbehinderungen
         * 17 WIND AND AIR QUALITY              Wind und Luftqualität
         * 18 ACTIVITIES                        Veranstaltungen
         * 19 SECURITY ALERTS                   Sicherheitsvorfälle
         * 20 DELAYS                            Zeitverluste
         * 21 CANCELLATIONS                     Ausfälle
         * 22 TRAVEL TIME INFORMATION           Reiseinformationen
         * 23 DANGEROUS VEHICLES                Gefährliche Fahrzeuge
         * 24 EXCEPTIONAL LOADS/VEHICLES        Außergewöhnliche Ladungen und Fahrzeuge
         * 25 TRAFFIC EQUIPMENT STATUS          Störungen an Lichtsignalanlagen u sonstigen Straßenausrüstungen
         * 26 SIZE AND WEIGHT LIMITS            Beschränkung der Fahrzeugmaße und -gewichte
         * 27 PARKING RESTRICTIONS              Parkregelungen
         * 28 PARKING                           Parken
         * 29 REFERENCE TO AUDIO BROADCASTS     Information
         * 30 SERVICE MESSAGES                  Service Meldungen
         * 31 SPECIAL MESSAGES                  Spezielle Meldungen
         */

        return match ($code) {
            11 => 'roadworks',
            14 => 'roadConditions',
            default => 'misc',
        };
    }
}
