# JuiceMediaChat

This Chat uses php-ratchet to create a websocket server and connects to it via javascript. The best: it's in Docker!

Start the server via 
```
docker run --add-host=host.docker.internal:host-gateway --name chat_server -v /var/www/vhosts/juicemedia.de/chat.juicemedia.de/server/config.php:/config.php chat_server
```

The parameter adds your local IP address to the database connection file so that you can make use of your local mySQL server (running on your docker-host).
