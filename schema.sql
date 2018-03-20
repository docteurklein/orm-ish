--
-- PostgreSQL database dump
--

-- Dumped from database version 10.3
-- Dumped by pg_dump version 10.3

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET client_min_messages = warning;
SET row_security = off;

ALTER TABLE ONLY public.product_attribute DROP CONSTRAINT product_attribute_product_id_fkey;
ALTER TABLE ONLY public.product_attribute DROP CONSTRAINT product_attribute_attribute_id_fkey;
ALTER TABLE ONLY public.attribute DROP CONSTRAINT attribute_family_id_fkey;
ALTER TABLE ONLY public.product_attribute DROP CONSTRAINT unique_assoc;
ALTER TABLE ONLY public.product DROP CONSTRAINT product_pkey;
ALTER TABLE ONLY public.family DROP CONSTRAINT family_pkey;
ALTER TABLE ONLY public.attribute DROP CONSTRAINT attribute_pkey;
DROP TABLE public.product_attribute;
DROP TABLE public.product;
DROP TABLE public.family;
DROP TABLE public.attribute;
SET default_tablespace = '';

SET default_with_oids = false;

--
-- Name: attribute; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.attribute (
    attribute_id uuid NOT NULL,
    family_id uuid
);


--
-- Name: family; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.family (
    family_id uuid NOT NULL,
    name text
);


--
-- Name: product; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.product (
    product_id uuid NOT NULL
);


--
-- Name: product_attribute; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.product_attribute (
    product_id uuid NOT NULL,
    attribute_id uuid NOT NULL
);


--
-- Name: attribute attribute_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.attribute
    ADD CONSTRAINT attribute_pkey PRIMARY KEY (attribute_id);


--
-- Name: family family_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.family
    ADD CONSTRAINT family_pkey PRIMARY KEY (family_id);


--
-- Name: product product_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.product
    ADD CONSTRAINT product_pkey PRIMARY KEY (product_id);


--
-- Name: product_attribute unique_assoc; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.product_attribute
    ADD CONSTRAINT unique_assoc PRIMARY KEY (product_id, attribute_id);


--
-- Name: attribute attribute_family_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.attribute
    ADD CONSTRAINT attribute_family_id_fkey FOREIGN KEY (family_id) REFERENCES public.family(family_id) DEFERRABLE;


--
-- Name: product_attribute product_attribute_attribute_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.product_attribute
    ADD CONSTRAINT product_attribute_attribute_id_fkey FOREIGN KEY (attribute_id) REFERENCES public.attribute(attribute_id) DEFERRABLE;


--
-- Name: product_attribute product_attribute_product_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.product_attribute
    ADD CONSTRAINT product_attribute_product_id_fkey FOREIGN KEY (product_id) REFERENCES public.product(product_id) DEFERRABLE;


--
-- PostgreSQL database dump complete
--

