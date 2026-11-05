## START
[What kind of server are you looking to run?]
-- Virtual machine / general-purpose server (EC2).
[Do you already have an existing setup, or are you starting from scratch?]
-- Starting from scratch.

Cette question parle des ressources AWS, 
pas de votre code. 
Votre application n’existe pas encore sur AWS, 
donc rien à migrer (option 2) 
et aucune ressource existante à reprendre (option 3). 
Comme pour la question précédente, 
elle ne crée rien et sert seulement à personnaliser l’accueil de la console.

[LAUNCH AN INSTANCE] https://463946129283-eeqzuz2o.eu-north-1.console.aws.amazon.com/ec2/home?region=eu-north-1#LaunchInstances:
En faite je recois 100$ de credit bouffe par chaque operation avec l'instance dont:
Instance type
t3.micro	Free tier eligible
Family: t3	2 vCPU	1 GiB Memory	Current generation: true
On-Demand Linux base pricing: 0.0108 USD per Hour 
On-Demand Windows base pricing: 0.02 USD per Hour 
On-Demand Ubuntu Pro base pricing: 0.0143 USD per Hour 
On-Demand RHEL base pricing: 0.0396 USD per Hour 
On-Demand SUSE base pricing: 0.0108 USD per Hour

[KEY PAIR] You can use a key pair to securely connect to your instance. Ensure that you have access to the selected key pair before you launch the instance.
When prompted, store the private key in a secure and accessible location on your computer. You will need it later to connect to your instance. 
name: tailor-key - RSA - .pem

[Network settings :]
Auto-assign public IP : Enable
Cochez Allow SSH traffic, Allow HTTPS traffic from the internet et Allow HTTP traffic from the internet.
Pour SSH, laissez provisoirement « Anywhere ». La connexion se fait uniquement par clé, ce qui reste raisonnable pour un apprentissage. Si vous choisissez « My IP », votre IP mobile peut changer et vous bloquer, et EC2 Instance Connect (la connexion depuis le navigateur) ne marcherait plus.

[STORAGE : 16Gio type gp3]
The selected AMI contains instance store volumes, however the instance does not allow any instance store volumes. None of the instance store volumes from the AMI will be accessible from the instance

[File system : None]

Point de contrôle : dans EC2 > Instances, l’état passe à « Running » puis « 2/2 checks passed » au bout de deux minutes environ.

[Launch Instance]

[Fixer l'adresse IP]
Sans cette étape, l’IP publique change à chaque arrêt et redémarrage de l’instance, et votre DNS casserait.
1. EC2 > menu de gauche > Elastic IPs > Allocate Elastic IP address > Allocate.
2. Sélectionnez l’adresse créée > Actions > Associate Elastic IP address > choisissez votre instance > Associate.(Instance : i-0c2b37f683c428667)
NB: I allowed its to be reassociated (public DNS: ec2-13-62-209-227.eu-north-1.com)

[CONNEXION]
chmod 600 ~/Telechargements/tailor-key.pem
ssh -i ~/Telechargements/tailor-key.pem ubuntu@13.62.209.227

```
Welcome to Ubuntu 24.04.5 LTS (GNU/Linux 7.0.0-1014-aws x86_64)
...
ubuntu@ip-172-31-35-203:~$ 

```
`172-31-35-203` : adresse privee interne AWS

[ALERTE DE BUDGET | MFA]

[Domain - subdomain : DuckDNS]
old: 102.244.43.205
new - myelasticIpAWS: 13.62.209.227
token 09dac509-a76c-45f5-a8c1-117f85804b16

puis `getent hosts dotsachasmart.duckdns.org` => ` 13.62.209.227   dotsachasmart.duckdns.org `
la commande affiche votre Elastic IP. N’utilisez pas ping pour tester, car le groupe de sécurité AWS bloque l’ICMP par défaut et ping resterait sans réponse même si le domaine est correct

L’adresse étant fixe grâce à l’Elastic IP, vous n’aurez pas besoin du jeton ni du script de mise à jour automatique de DuckDNS

`Dans lancer_local.sh, ajoutez export APP_ENV='local'`
Ainsi le même code tourne en local et en production sans modification, ce qui est le but d’un déploiement reproductible

[EMAIL dans deploy.env]
Dans le script, elle est transmise à certbot (-m "$EMAIL"), qui l’utilise pour créer votre compte chez Let’s Encrypt, l’organisme qui délivre le certificat HTTPS gratuit. Elle sert de contact pour ce compte.
