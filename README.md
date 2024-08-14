# Blog Management API

## Overview

This project is a Symfony-based API for managing blog articles. It consists of two parts:

1. **Symfony API Creation**: Build a RESTful API for CRUD operations on blog articles.
2. **Algorithmic Challenge**: Implement a function to find the most frequently occurring words in a text, excluding banned words.

The project demonstrates Symfony best practices and includes authentication, validation, and testing.

## Repository Details

- **Repository Name**: Sobrus-symfony-api-test
- **Public Repository**: [Repository Link](#) (Replace `#` with the actual link once it's available)
- **Email for Submission**: nissrine.m@sobrus.com

## Table of Contents

- [Setup](#setup)
- [Part 1: Symfony API Creation](#part-1-symfony-api-creation)
- [Part 2: Algorithmic Challenge](#part-2-algorithmic-challenge)
- [Testing](#testing)
- [Documentation](#documentation)
- [Contributing](#contributing)
- [License](#license)

## Setup

1. **Clone the Repository**
    ```bash
    git clone https://github.com/your-username/Sobrus-symfony-api-test.git
    cd Sobrus-symfony-api-test
    ```

2. **Install Dependencies**
    ```bash
    composer install
    ```

3. **Setup Environment**
   - Copy the `.env.example` file to `.env` and configure your environment variables.

4. **Create Database**
    ```bash
    php bin/console doctrine:database:create
    php bin/console doctrine:schema:update --force
    ```

5. **Run the Symfony Server**
    ```bash
    symfony server:start
    ```

## Part 1: Symfony API Creation

### Project Setup

- **Symfony Project Initialization**: Set up a new Symfony project using Symfony CLI or Composer.

### Entity

- **BlogArticle Entity**: Defined with the following fields:
   - `authorId` (INT)
   - `title` (VARCHAR(100))
   - `publicationDate` (DATETIME)
   - `creationDate` (DATETIME)
   - `content` (TEXT)
   - `keywords` (JSON)
   - `status` (ENUM: ‘draft’, ‘published’, ‘deleted’)
   - `slug` (VARCHAR(255))
   - `coverPictureRef` (VARCHAR(255)) (Uploaded and saved in the `public/uploaded_pictures` folder)

### CRUD Endpoints

- **POST** `/blog-articles` - Create a new blog article.
- **GET** `/blog-articles` - List all blog articles.
- **GET** `/blog-articles/{id}` - Get details of a specific blog article.
- **PATCH** `/blog-articles/{id}` - Update a blog article.
- **DELETE** `/blog-articles/{id}` - Soft delete a blog article.

### Validation

- Use Symfony’s validation component to ensure data integrity.

### Authentication

- Implement JWT authentication for secure API access.

## Part 2: Algorithmic Challenge

### Problem

- Implement a function to find the 3 most frequently occurring words in a text, excluding a list of banned words.

### Usage

- **Update Blog Article Keywords**: Utilize the function to add or update keywords in blog articles.
- **Content Validation**: Validate the content of blog articles against a list of banned words.

## Testing

- **Unit Tests**: Write tests for individual components.
- **Functional Tests**: Ensure API endpoints work as expected.
- Run tests using PHPUnit:
    ```bash
    php bin/phpunit
    ```

## Documentation

- **API Documentation**: Document the API using Swagger. Access the documentation at `/api/docs`.