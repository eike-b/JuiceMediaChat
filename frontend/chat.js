let url;
let conn;
var username;

let moderated = undefined;
let moderator = false;
let userId = 0;

let chatMessages;

//TODO wenn user oft refreshed -> möchtest du einen neuen username?

function setConnectionParams() {
    let getParams = {};
    url = new URL(window.location.href);
    if(url.searchParams.has('token')) {
        getParams.token = url.searchParams.get('token');
    }
    getParams.username = hasActiveUsername();
    return new URLSearchParams(getParams).toString();
}

function hasActiveUsername() {
    if(!window.sessionStorage.getItem("username")) {
        return false;
    }
    else {
        window.username = window.sessionStorage.getItem("username");
        return window.username;
    }
}

function hasUserColor() {
    if(!window.sessionStorage.getItem("usercolor")) {
        return false;
    }
    else {
        window.usercolor = window.sessionStorage.getItem("usercolor");
        return window.usercolor;
    }
}

function establishConnection() {

    if(!hasActiveUsername()) {
        return;
    }

    let p = setConnectionParams();
    conn = new WebSocket('ws://192.168.178.182:1337?' + p);
    conn.onerror = function (e){
        usernamePopup("error");
        console.error("websocket kapoot");
    };

    conn.onopen = onConnectionOpen;
    conn.onmessage = onMessage;

    if(!hasUserColor()) {
        let colors = ["#8FB0E9", "#638FDA", "#215BBF", "#4174CC", "#CC6555", "#FFC6BB", "#F49A8C", "#A94030"];
        window.usercolor = colors[Math.floor(Math.random() * colors.length)];
        window.sessionStorage.setItem("usercolor", window.usercolor);
    }




}

function usernamePopup(toggle = "") {
    let usernamePopup = document.querySelector("#usernamePopup");

    if(toggle === "") {
        if (usernamePopup.dataset.visible) {
            toggle = "hide";
        }
        else {
            toggle = "show";
        }
    }

    switch (toggle) {
        case "show":
            usernamePopup.style.visibility = "visible";
            usernamePopup.dataset.visible = "true";
            break;
        case "hide":
            usernamePopup.style.visibility = "hidden";
            usernamePopup.dataset.visible = "false";
            usernamePopup.dataset.visible = "false";
            break;
        case "error":
            usernamePopup.querySelector(".errorMessage").style.opacity = "1";
            break;
    }

}

function enterWithUsername() {
    let input = document.querySelector("#usernamePopup input");
    window.username = input.value;
    if(username.trim() !== "") {
        window.sessionStorage.setItem("username", username);
        establishConnection();
    }
    else {
        input.value = "";
    }

}

function enterAnonymous() {
    //let anonymous = ["Fuchs", "Wolf", "Löwe", "Otter", "Tiger", "Seebär", "Fisch", "Luchs", "Panther"];
    //window.username = "Anonymer " + anonymous[Math.floor(Math.random() * anonymous.length)];
    window.username = "Anonym";
    window.sessionStorage.setItem("username", window.username);
    establishConnection();
}



// let's fetz
document.addEventListener("DOMContentLoaded", function (e) {

    usernamePopup("show");

    // eventlistener für chat-mit-username button und enter taste
    document.querySelector("#enterWithUsername").addEventListener("click", enterWithUsername);
    document.querySelector("input[name=username]").onkeydown = function(e) {
        if (e.key === "Enter") { enterWithUsername(); }
    };

    //eventlistener für anonym-chat
    document.querySelector("#enterAnonymous").addEventListener("click", enterAnonymous);

    establishConnection();




});

function onConnectionOpen(e) {
    console.log(window.username);
    usernamePopup("hide");
    document.querySelector("input[name=msg]").onkeydown = onEnter;
    document.querySelector("#sendMessage").addEventListener("click", onEnter);
}

function onMessage(e) {

    let jsonMessage = JSON.parse(e.data);
    console.log(jsonMessage);

    switch (jsonMessage.command) {
        case "chatMsg":
            let chatMessage;

            // Eigene Chat Nachricht
            if(jsonMessage.senderId == userId) {
                chatMessage = chatMessageBuilder(jsonMessage.username, jsonMessage.timestamp, jsonMessage.message, true, jsonMessage.uuid);
                if(!moderator) {
                    chatMessage.classList.add("queued");
                }
            }
            else {
                chatMessage = chatMessageBuilder(jsonMessage.username, jsonMessage.timestamp, jsonMessage.message, false, jsonMessage.uuid);
            }

            if(moderator) {
                // Wenn ein Moderator eine bereits approved Nachricht erhält
                // Nachricht als approved markieren

                if (jsonMessage.approved == 1) {
                    chatMessage.classList.remove("queued");
                    chatMessage.classList.add("approved");
                    chatMessage.querySelector(".moderatorView button[data-function=approve]").setAttribute("disabled", "disabled");
                    chatMessage.querySelector(".moderatorView button[data-function=approve]").innerHTML = "freigegeben";
                } else {
                    chatMessage.classList.add("queued");
                }

                // Wenn ein Moderator eine bereits gelöschte Nachricht erhält
                // Nachricht als gelöscht markieren + freigeben und pushen deaktivieren
                if (jsonMessage.deleted == 1) {


                    chatMessage.classList.add("deleted");
                    chatMessage.querySelector(".chatText").innerHTML = "Diese Nachricht wurde gelöscht";
                    chatMessage.querySelector(".moderatorView button[data-function=delete]").setAttribute("disabled", "disabled");
                    chatMessage.querySelector(".moderatorView button[data-function=approve]").setAttribute("disabled", "disabled");
                    chatMessage.querySelector(".moderatorView button[data-function=push]").setAttribute("disabled", "disabled");

                }

                // Wenn ein Moderator eine bereits pushed Nachricht erhält
                // Nachricht als gelöscht markieren
                if (jsonMessage.pushed == 1) {
                    chatMessage.classList.add("pushed");
                    chatMessage.querySelector(".moderatorView button[data-function=push]").setAttribute("disabled", "disabled");
                    chatMessage.querySelector(".moderatorView button[data-function=done]").removeAttribute("disabled");
                    chatMessage.querySelector(".moderatorView button[data-function=push]").innerHTML = "pushed";
                }

                // Wenn ein Moderator eine bereits done Nachricht erhält
                // Nachricht als done markieren
                if (jsonMessage.done == 1) {
                    chatMessage.classList.add("pushed");
                    chatMessage.querySelector(".moderatorView button[data-function=done]").setAttribute("disabled", "disabled");
                    chatMessage.querySelector(".moderatorView button[data-function=done]").innerHTML = "done";
                }
            }
            // Fremde Chat nachricht


            let chatInnerWrapper = document.querySelector("#chatInnerWrapper");
            chatInnerWrapper.appendChild(chatMessage);
            chatInnerWrapper.scrollTop = chatInnerWrapper.scrollHeight;

            break;

        case "approveMsg":
            chatMessages = document.getElementsByClassName("chatMessage");
            for (let i = 0; i < chatMessages.length; i++) {
                if(chatMessages[i].dataset.uuid == jsonMessage.uuid) {
                    chatMessages[i].classList.remove("queued");
                    chatMessages[i].classList.add("approved");

                    if(moderator) {
                        chatMessages[i].querySelector(".moderatorView button[data-function=approve]").setAttribute("disabled", "disabled");
                        chatMessages[i].querySelector(".moderatorView button[data-function=approve]").innerHTML = "freigegeben";
                    }
                }
            }
            break;

        case "delMsg":
            chatMessages = document.getElementsByClassName("chatMessage");
            for (let i = 0; i < chatMessages.length; i++) {
                if(chatMessages[i].dataset.uuid == jsonMessage.uuid) {
                    chatMessages[i].classList.add("deleted");
                    chatMessages[i].querySelector(".chatText").innerHTML = "Diese Nachricht wurde gelöscht";

                    if(moderator) {
                        chatMessages[i].querySelector(".moderatorView button[data-function=delete]").setAttribute("disabled", "disabled");
                        chatMessages[i].querySelector(".moderatorView button[data-function=approve]").setAttribute("disabled", "disabled");
                        chatMessages[i].querySelector(".moderatorView button[data-function=push]").setAttribute("disabled", "disabled");
                    }
                }
            }
            break;

        case "pushMsg":
            chatMessages = document.getElementsByClassName("chatMessage");
            for (let i = 0; i < chatMessages.length; i++) {
                if(chatMessages[i].dataset.uuid == jsonMessage.uuid) {
                    chatMessages[i].classList.add("pushed");

                    if(moderator) {
                        chatMessages[i].querySelector(".moderatorView button[data-function=push]").setAttribute("disabled", "disabled");
                        chatMessages[i].querySelector(".moderatorView button[data-function=push]").innerHTML = "pushed";
                        chatMessages[i].querySelector(".moderatorView button[data-function=done]").removeAttribute("disabled");
                    }
                }
            }
            break;

        case "doneMsg":
            chatMessages = document.getElementsByClassName("chatMessage");
            for (let i = 0; i < chatMessages.length; i++) {
                if(chatMessages[i].dataset.uuid == jsonMessage.uuid) {
                    if(moderator) {
                        chatMessages[i].querySelector(".moderatorView button[data-function=done]").setAttribute("disabled", "disabled");
                        chatMessages[i].querySelector(".moderatorView button[data-function=done]").innerHTML = "done";
                    }
                    else {
                        chatMessages[i].remove();
                    }


                }

            }
            break;

        case "settings":
            moderated = (jsonMessage.moderated == 'true');
            moderator = Boolean(jsonMessage.userIsModerator);
            userId = jsonMessage.userId;
            if(moderator) {
                window.username = "Moderator";
            }
            break;
    }



}

function onEnter(e) {
    if (e.key !== "Enter" && e.type !== "click") {
       return;
    }
    let input = document.querySelector("input[name=msg]");

    if(input.value !== "") {
        let data = {command: 'chatMsg', username: window.username, message: input.value};
        conn.send(JSON.stringify(data));
        input.value = '';
    }

}

function chatMessageBuilder(username = '', timestamp = '13:37', message = '', self = false, uuid = '', senderId = '') {

    let chatMessage = document.createElement("div");
    chatMessage.dataset.uuid = uuid;
    chatMessage.dataset.senderId = senderId;
    chatMessage.onclick = ()=>{void(0)};

    let chatMessageBody = document.createElement("div");
    chatMessageBody.className = "chatMessageBody";

    let userBubble = document.createElement("div");
    userBubble.className = "userBubble";
    let colors = ["#8FB0E9"];
    userBubble.style.background = colors[Math.floor(Math.random() * colors.length)];
    let split = username.split(" ");
    let initials;
    if(split.length === 1) {
        initials = split[0].substr(0,1);
    } else {
        initials = split[0].substr(0,1) + split[split.length-1].substr(0,1);
    }
    userBubble.dataset.username = initials.toUpperCase();

    let chatUser = document.createElement("div")
    chatUser.className = "chatUser";
    chatUser.innerText = username;


    let chatTimestamp = document.createElement("div")
    chatTimestamp.className = "chatTimestamp";

    let dateTime = new Date(timestamp*1000);
    chatTimestamp.innerText = dateTime.toLocaleTimeString().substr(0, 5);

    let approvedIdentifier = document.createElement("div");
    approvedIdentifier.className = "approvedIdentifier";


    let chatText = document.createElement("div");
    chatText.className = "chatText";
    chatText.innerText = message;


    let moderatorView = document.createElement("div");
    if(moderator) {


        moderatorView.className = "moderatorView";


        let approveButton = document.createElement("button");
        approveButton.innerText = "Freigeben";
        approveButton.title = "Die Nachricht wird für alle Teilnehmer freigegeben."
        approveButton.className = "button";
        approveButton.dataset.uuid = uuid;
        approveButton.dataset.function = "approve";
        approveButton.addEventListener("click", function (e) {

            conn.send(JSON.stringify({command: "approveMsg", uuid: e.target.dataset.uuid}));
            //e.target.setAttribute("disabled", true);
            //console.log(this.dataset.uuid)
        });





        let pushButton = document.createElement("button");
        pushButton.innerText = "Push";
        pushButton.title = "Diese Nachricht an den Speaker senden."
        pushButton.className = "button";
        pushButton.dataset.uuid = uuid;
        pushButton.dataset.function = "push";
        pushButton.addEventListener("click", function (e) {
            e.preventDefault();
            conn.send(JSON.stringify({command: "pushMsg", uuid: e.target.dataset.uuid}));

            //console.log(this.dataset.uuid)
        });

        let deleteButton = document.createElement("button");
        deleteButton.innerText = "Löschen";
        deleteButton.title = "Diese Nachricht als gelöscht markieren (entfernt sie jedoch nicht aus der Datenbank).";
        deleteButton.href = "";
        deleteButton.className = "button";
        deleteButton.dataset.uuid = uuid;
        deleteButton.dataset.function = "delete";
        deleteButton.addEventListener("click", function (e) {
            e.preventDefault();
            conn.send(JSON.stringify({command: "delMsg", uuid: e.target.dataset.uuid}));
            //console.log(this.dataset.uuid)
        });
        
        let doneButton = document.createElement("button");
        doneButton.innerText = "Done";
        doneButton.title = "Nachricht wird beim Speaker wieder ausgeblendet.";
        doneButton.href = "";
        doneButton.disabled = true;
        doneButton.className = "button";
        doneButton.dataset.uuid = uuid;
        doneButton.dataset.function = "done";
        doneButton.addEventListener("click", function (e) {
            e.preventDefault();
            conn.send(JSON.stringify({command: "doneMsg", uuid: e.target.dataset.uuid}));
            //console.log(this.dataset.uuid)
        });


        moderatorView.append(approveButton, pushButton, doneButton, deleteButton);

    }

    if(self) {
        chatMessage.className = "chatMessage self";
        userBubble.style.background = window.usercolor;
    } else {
        chatMessage.className = "chatMessage";
    }

    chatMessageBody.append(chatUser, chatTimestamp, approvedIdentifier, chatText, moderatorView);
    chatMessage.append(userBubble, chatMessageBody);


    return chatMessage;
}
