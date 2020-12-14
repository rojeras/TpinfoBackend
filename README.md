# Historik 

## 2018-06-11

Detta repo skapas. Det består av back-end filerna från de [tidigare hippo-repot på bitbucket](https://bitbucket.org/lars_erik_r_jer_s/hippo/src/master/ ): 

```
commit 096eb268fa3e88c006e1fa042abda0d9437d93c4 (HEAD -> master, origin/master, origin/HEAD)
Author: Lars Erik Röjerås <lars.erik.rojeras@skoview.se>
Date:   Tue Jun 5 14:35:50 2018 +0200

    Version in production 2018-06-02
```

I denna första commit i detta nya repo har filerna ännu inte anpassats till den förändrade mappstrukturen.

##Useful docker commands
```
docker container ls # Lista exekverande containers  
docker container ls -a # Lista alla containers  
docker image rm -f $(docker image ls -q) # Tag bort alla images  
docker pull rojeras/tpinfo-backend:latest # Läs ner image från docker hub  
docker tag rojeras/tpinfo-frontend:latest docker-registry.centrera.se:443/frontend # Tagga imagen för att göra det möjligt att pusha till NGs registry  
docker push docker-registry.centrera.se:443/frontend # Pusha en taggad image till NGs docker registry  
docker pull docker-registry.centrera.se:443/backend # Läs ner imagen från NGs registry (ex till tpinfo 
-servrarna) 
docker build --rm -t back5 . # Tag bort container back5 och återskapa imagen  
docker run --env-file=../backend-envir.lst -p 8081:80 back5 # Kör backend med portar, miljövariabler  
docker run -it back5 /bin/bash # Kör image back5 och ge kontroll till bash i container  
docker exec -it 3d48b2e5d748 /bin/bash # Attach and start bash in a running container  
docker save -o backend-image.tar rojeras/tpinfo-backend:latest # Save an image to a tar file  
docker load -o filename.tar # Load an image from a tar file 
```

