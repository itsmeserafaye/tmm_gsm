@echo off
set GIT_PATH="C:\Program Files\Git\cmd\git.exe"

echo Configuring Git Identity...
%GIT_PATH% config user.email "dev@tmm-gsm.com"
%GIT_PATH% config user.name "TMM Developer"

echo Initializing repository...
%GIT_PATH% init

echo Setting up remote...
%GIT_PATH% remote remove origin 2>nul
%GIT_PATH% remote add origin https://github.com/itsmeserafaye/tmm_gsm.git

echo Switching to branch ParTer...
%GIT_PATH% checkout -b ParTer 2>nul
if %errorlevel% neq 0 %GIT_PATH% checkout ParTer

echo Staging files...
%GIT_PATH% add .

echo Committing changes...
%GIT_PATH% commit -m "feat: Update Parking & Terminal Management Module with Card UI and Database"

echo Pushing to GitHub...
%GIT_PATH% push -u origin ParTer

echo Done.
