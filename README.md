# 後期課題 制作物 EC2 デプロイ手順書
## 1. Docker / Docker Compose インストール
### システムの更新
```sh
sudo dnf update -y
```
### Docker 本体のインストール
```
sudo dnf install -y docker
```
### Dockerの起動設定
```sh
sudo systemctl enable docker
sudo systemctl start docker
```
### Docker Composeのインストール
```sh
sudo mkdir -p /usr/local/lib/docker/cli-plugins/
sudo curl -SL https://github.com/docker/compose/releases/download/v2.36.0/docker-compose-linux-x86_64 -o /usr/local/lib/docker/cli-plugins/docker-compose
```
### 権限の設定
```sh
sudo chmod +x /usr/local/lib/docker/cli-plugins/docker-compose
sudo usermod -aG docker ec2-user
```
一度ログアウトして、再度ログインする

## 2. gitのインストール & ソースコードの取得
### gitのインストール
```sh
sudo yum install git -y
```
### ソースコードの取得
```sh
git clone https://github.com/ZeroRein/aw-edu02.git
```
## 3. ビルド & 起動
```sh
cd aw-edu02
docker compose build
docker compose up -d
```
