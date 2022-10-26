<?php
namespace chatApp;
use chatApp\DB;
use JetBrains\PhpStorm\Pure;
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;


class Chat implements MessageComponentInterface {

    protected array $messageQueue;
    protected array $_user;
    protected array $_blockedUser;
    protected array $_settings;

    public function __construct()
    {
        echo "starte...".PHP_EOL;
        $this->syncQueueWithDatabase();
        $this->syncSettings();
        $this->_user = [];
        $this->_blockedUser = [];
        echo "...fertig!".PHP_EOL;
    }

    private function syncQueueWithDatabase()
    {
        echo "lese Nachrichten aus Datenbank..".PHP_EOL;
        //TODO nur Nachrichten vom aktuellen Tag laden
        if (DB::getInstance()->query("SELECT * FROM messages WHERE approved = :approved", ["approved" => 0])) {
            if(!empty(DB::getInstance()->results()))
            {
                unset($this->messageQueue);
                foreach (DB::getInstance()->results() as $msg) {
                    $this->messageQueue[] = array_merge(["command" => "chatMsg"], $msg);
                }
                echo "Ich habe " . count($this->messageQueue) . " Nachrichten aus der Datenbank geladen.".PHP_EOL;
            }
            else {
                $this->messageQueue = [];
                echo "Ich habe 0 Nachrichten aus der Datenbank geladen.".PHP_EOL;
            }

        } else {
            $this->messageQueue = [];
            echo "Fehler beim lesen der Nachrichten aus der Datenbank.".PHP_EOL;
        }

    }

    private function syncSettings() {
        echo "lese Einstellungen aus Datenbank..".PHP_EOL;
        if (DB::getInstance()->query("SELECT * FROM chat_settings")) {
            $results = DB::getInstance()->results();
            if(!empty($results))
            {
                unset($this->_settings);
                $this->_settings['welcome_message'] = $results[0]['welcome_message'];
                $this->_settings['moderation'] = $results[0]['moderation'];
                echo "Ich habe die Einstellungen mit der Datenbank synchronisiert.".PHP_EOL;
            }

        } else {
            $config = include('../config.php');
            $this->_settings['welcome_message'] = $config['welcome_message_default'];
            $this->_settings['moderation'] = $config['moderation'];
            echo "Fehler beim synchronisieren der Einstellungen. Defaults wurden geladen.".PHP_EOL;
        }


    }

    /**
     * Fügt einen neuen User in das user-Array hinzu.
     * @param int $id
     * @param string $username
     * @param bool $isModerator
     * @param bool $isSpeaker
     * @param $connection
     */
    private function addUser(int $id, string $username, bool $isModerator, bool $isSpeaker, $connection) {
        $this->_user[$id] = [
            "username" => $username,
            "id" => $id,
            "isModerator" => $isModerator,
            "isSpeaker" => $isSpeaker,
            "connection" => $connection,
        ];
    }

    /**
     * Löscht einen User aus dem user-Array.
     * @param int $id userId des zu löschenden users
     */
    private function dropUser(int $id) {
        unset($this->_user[$id]);
    }

    private function consoleEcho($message, $direction = "", $sentAt = "") {
        if(!empty($sentAt)) {
            $sentAt = "to $sentAt Receivers";
        }
        echo match ($direction) {
            "in" => "inbound: " . $message . PHP_EOL,
            "out" => "outbound $sentAt: " . $message . PHP_EOL,
            default => $message . PHP_EOL,
        };
    }

    private function sendTo($userId, $msg) {
        if(key_exists($userId, $this->_user)) {
            $this->_user[$userId]['connection']->send($msg);
        }
        else {
            $this->consoleEcho("User nicht (mehr) da.");
        }

    }

    private function sendToAllMods($msg) {
        $this->consoleEcho($msg, "out");
        foreach ($this->_user as $userId => $user) {
            if ($user['isModerator']) {
                $this->sendTo($userId, $msg);
            }
        }
    }

    private function sendToAllSpeakers($msg) {
        $this->consoleEcho($msg, "out");
        foreach ($this->_user as $userId => $user) {
            if ($user['isSpeaker']) {
                $this->sendTo($userId, $msg);
            }
        }
    }

    private function sendToAllUsers($msg, $except = []) {
        $sent = 0;
        foreach ($this->_user as $userId => $user) {
            if(!$user['isModerator'] && !$user['isSpeaker'] && !in_array($userId, $except)) {
                $this->sendTo($userId, $msg);
                $sent++;
            }
        }
        $this->consoleEcho($msg, "out", $sent);
    }

    private function sendToAll(array $receivers, $msg, $except = []): void
    {
        if(!empty($receivers AND $msg)) {
            if(in_array("users", $receivers)) {
                $this->sendToAllUsers($msg, $except);
            }
            if(in_array("mods", $receivers)) {
                $this->sendToAllMods($msg);
            }
            if(in_array("speakers", $receivers)) {
                $this->sendToAllSpeakers($msg);
            }
        }
    }

    private function userIsModerator($userId): bool {
        return $this->_user[$userId]['isModerator'];
    }

    private function userIsSpeaker($userId): bool {
        return $this->_user[$userId]['isSpeaker'];
    }

    /**
     * Erzeugt ein Nachrichtenarray aus vorgegebener Nachricht (z.B. aus DB).
     * @param array $msg assoc. Array mit Nachricht
     * @return array|bool assoc Array mit finaler Nachricht (ohne DB Felder)
     */
    private function messageBuilder(array $msg): array|bool
    {
        if(!empty($msg)) {
            return [
                "command" => "chatMsg",
                "uuid" => $msg['uuid'],
                "timestamp" => $msg['timestamp'],
                "senderId" => $msg['senderId'],
                "username" => $msg['username'],
                "message" => $msg['message']
            ];
        }
        return false;
    }

    /**
     * Speichert eine Nachricht in der lokalen queue und in der DB.
     * @param $msg array Nachricht
     * @param bool $pushed
     * @param bool $approved
     * @return void
     */
    private function messageToDatabase(array $msg, bool $approved = false, bool $pushed = false): void {
        DB::getInstance()->insert("messages", [
            "uuid" => $msg['uuid'],
            "senderId" => $msg['senderId'],
            "username" => $msg['username'],
            "timestamp" => $msg['timestamp'],
            "message" => $msg['message'],
            "pushed" => $pushed,
            "approved" => $approved
        ]);
    }

    /**
     * Message in queue speichern
     * @param array $msg Nachricht
     * @return void
     */
    private function messageToQueue(array $msg): void {
        $this->messageQueue[] = $msg;
    }

    private function getMessageHistory(): array|false {
        $db = DB::getInstance()->query("SELECT * FROM `messages` WHERE `deleted` = 0 LIMIT 25");
        if(!$db->isError()) {
            return $db->results();
        }
        return false;
    }

    function onOpen(ConnectionInterface $conn)
    {

        //GET string wird geparsed und in Array übertragen
        parse_str($conn->httpRequest->getUri()->getQuery(), $getParams);

        // nur user mit username annehmen
        if(isset($getParams['username']) && $getParams['username'] != "") {
            $username = $getParams['username'];
            $id = $conn->resourceId;

            if(isset($getParams['token'])) {
                // und der token param "supergeheim" ist
                if($getParams['token'] == 'supergeheim') {
                    // user als moderator eintragen
                    $this->addUser($id, $username, true, false, $conn);
                    echo "Es hat sich ein Moderator verbunden. ($id)".PHP_EOL;
                }
                elseif ($getParams['token'] == 'speaker') {
                    // user als speaker eintragen
                    $this->addUser($id, $username, false, true, $conn);
                    echo "Es hat sich ein Speaker verbunden. ($id)".PHP_EOL;
                }
            }
            else {
                // normalen user eintragen
                $this->addUser($id, $username, false, false, $conn);
                echo "Es hat sich ein User verbunden. ($id)".PHP_EOL;
            }

            // jedem neuen client aktuelle config mitgeben
            $this->sendTo($id, json_encode([
                "command" => "settings",
                "moderation" => $this->_settings['moderation'],
                "userId" => $id,
                "userIsModerator" => $this->userIsModerator($id),
                "userIsSpeaker" => $this->userIsSpeaker($id),
            ]));


            if($this->_settings['moderation']) {
                // jedem neuen Moderator alle Nachrichten in Warteschlange schicken
                if($this->userIsModerator($id)) {

                    // Wenn die Nachrichtenhistory fehlerfrei gelesen wird
                    if($msgToMod = $this->getMessageHistory()) {
                        $this->consoleEcho("Message History geladen");
                    }
                    // Ansonsten wird die message queue genutzt
                    else {
                        // Einmal alle Nachrichten in der Queue mit der Datenbank abgleichen
                        $this->syncQueueWithDatabase();
                        $msgToMod = $this->messageQueue;
                        $this->consoleEcho("Fehler beim Laden der MessageHistroy aus der MySQL Datenbank. -> Lade stattdessen MessageQueue");
                    }

                    foreach ($msgToMod as $key => $msg) {
                        $conn->send(json_encode(array_merge(["command" => "chatMsg"], $msg)));
                    }
                }
                elseif ($this->userIsSpeaker($id)) {
                    // Einmal alle Nachrichten in der Queue mit der Datenbank abgleichen

                    // Filtern und nur die Nachrichten mit "pushed" status an speaker schicken
                    foreach ($this->getMessageHistory() as $key => $queueMessage) {
                        if($queueMessage["pushed"] && !$queueMessage["done"]) {
                            $conn->send(json_encode(array_merge(["command" => "chatMsg"], $queueMessage)));
                        }
                    }
                }
                else {
                    // jedem neuen User die "moderated" Nachricht schicken
                    $this->sendTo($id, json_encode([
                        "command" => "chatMsg",
                        "username" => "Moderator",
                        "approved" => true,
                        "timestamp" => time(),
                        "message" => $this->_settings['welcome_message']
                    ]));
                }

            }

        }
        else {
            // User ohne Username bekommt nix, was fällt dem auch ein
            echo "User ohne username!!".PHP_EOL;
        }
    }

    function onClose(ConnectionInterface $conn)
    {
        $this->dropUser($conn->resourceId);
        echo "Client {$conn->resourceId} hat die Verbindung getrennt.\n";
    }

    function onError(ConnectionInterface $conn, \Exception $e)
    {
        echo "Es ist ein Fehler aufgetreten. {$e->getMessage()}\n";

    }

    function onMessage(ConnectionInterface $from, $msg)
    {

        $user = $this->_user[$from->resourceId];
        //echo "\033[38;5;10mincoming: " . $msg . PHP_EOL;
        echo "incoming: " . $msg . PHP_EOL;

        // jede nachricht wird decodiert und in assoc array gewandelt
        $message = json_decode($msg, true);

        // Jetzt wird zwischen den verschiedenen Nachrichtentypen unterschieden
        switch ($message['command']) {
            // Normale Chat Nachricht
            case 'chatMsg':

                // bei chat nachricht wird eine uuid erzeugt und ins array geschoben
                // ebenso absender ID
                $message["uuid"] = uniqid();
                $message["senderId"] = $user['id'];
                $message["timestamp"] = time(); //TODO mysql datetime für Datenbank verwenden


                // wenn der chat auf moderiert eingestellt ist
                if($this->_settings['moderation']) {


                    if(!$user['isModerator']) {

                        // Nachricht in die queue schreiben
                        $this->messageToQueue($message);

                        // Nachricht an Sender zurück, damit er sie darstellen kann
                        $this->sendTo($user['id'], json_encode($message));

                        // Jedem Moderator die Nachricht zustellen
                        $this->sendToAllMods(json_encode($message));

                        // Nachricht in die db schreiben aber als "nicht approved" markieren
                        $this->messageToDatabase($message);
                    }
                    else {
                        $message['approved'] = true;
                        // Nachricht in die DB schreiben aber direkt als approved markieren
                        $this->messageToDatabase($message,true);
                        // Wenn die Nachricht von einem Moderator kommt, jedem direkt zustellen (ausser speaker)
                        $this->sendToAll(["users", "mods"], json_encode($message));
                    }
                }
                else {
                    $this->messageToDatabase($message,true);
                    $this->sendToAllUsers(json_encode($message));
                }
                break;

            // Befehl zum approven einer queued message
            case 'approveMsg':

                // Absender ist nicht in der Liste der Moderatoren, abbruch!
                if(!$user['isModerator']) {
                    echo "User nicht Moderator, abbruch!".PHP_EOL;
                    break;
                }

                $uuid = $message['uuid'];
                $messageFromDB = DB::getInstance()->getMessage(["uuid" => $message['uuid']]);


                if($messageFromDB['approved'] == 1) {
                    echo "Nachricht wurde bereits approved, abbruch!".PHP_EOL;
                    break;
                }
                $messageToApprove = $this->messageBuilder($messageFromDB);



                $senderId = $messageFromDB['senderId'];
                $modId = $user['id'];

                if($messageToApprove) {
                    // Nachricht an alle Clients (ausser dem Absender und dem mod) senden
                    $this->sendToAll(["users"], json_encode($messageToApprove), [$senderId]);
                    // der Urheber bekommt approved update
                    $this->sendTo($messageFromDB['senderId'], json_encode(["command" => "approveMsg", "uuid" => $message['uuid']]));
                    // moderatoren auch
                    $this->sendToAllMods(json_encode(["command" => "approveMsg", "uuid" => $message['uuid']]));
                    // Nachricht in der Datenbank als pushed markieren
                    DB::getInstance()->update("messages", ["approved" => true,], ["uuid" => $message['uuid']]);
                    // Nachricht aus queue löschen
                    if($key = array_search($uuid, $this->messageQueue)) {
                        unset($this->messageQueue[$key]);
                    }

                }

                break;

            case 'pushMsg':

                $messageFromDB = DB::getInstance()->getMessage(["uuid" => $message['uuid']]);
                if($messageFromDB['pushed'] == 1) {
                    echo "Nachricht wurde bereits gepushed, abbruch!".PHP_EOL;
                    break;
                }
                $messageToPush = $this->messageBuilder($messageFromDB);
                if($messageToPush) {
                    // Nachricht an alle Speaker senden
                    $this->sendToAllSpeakers(json_encode($messageToPush));
                    // moderatoren bekommen push update
                    $this->sendToAllMods(json_encode(["command" => "pushMsg", "uuid" => $message['uuid']]));
                    // Nachricht in der Datenbank als pushed markieren
                    DB::getInstance()->update("messages", ["pushed" => true,], ["uuid" => $message['uuid']]);
                }

                break;

            case 'delMsg':
                // alle bekommen delete command
                $this->sendToAll(["users", "mods", "speaker"], json_encode(["command" => "delMsg", "uuid" => $message['uuid']]));
                // Nachricht in der Datenbank als pushed markieren
                DB::getInstance()->update("messages", ["deleted" => true,], ["uuid" => $message['uuid']]);
                break;

            case 'blockUser':
                $messageFromDB = DB::getInstance()->getMessage(["uuid" => $message['uuid']]);
                if(key_exists($messageFromDB['senderId'], $this->_user)) {
                    $ip = $this->_user[$messageFromDB['senderId']]['connection']->remoteAddress;
                    $this->_blockedUser[] = $ip;
                    echo "USER BLOCKED: $ip".PHP_EOL;
                }
                //TODO sicher, dass wir hier IPs blocken sollten?
                // vielleicht lieber resourceId blocken

                break;

            case 'doneMsg':
                // alle bekommen done command
                $this->sendToAll(["mods", "speakers"], json_encode(["command" => "doneMsg", "uuid" => $message['uuid']]));
                // Nachricht in der Datenbank als done markieren
                DB::getInstance()->update("messages", ["done" => true,], ["uuid" => $message['uuid']]);
                break;

            // settings umstellen
            case 'settings':
                $this->syncSettings();
                echo "settings".PHP_EOL;
                break;
        }
    }




}